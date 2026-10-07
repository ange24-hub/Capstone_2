"""Read-only audit of the household shapefile against the app's GeoJSON.

Usage: python scripts/audit_gis_source.py <Household_Population_Bldg_Pts.shp>
Uses only Python's standard library. No registry records are changed.
Projection reference: https://proj.org/en/stable/operations/projections/tmerc.html
"""

import argparse
import collections
import json
import math
from pathlib import Path
import struct
import sys


def utm51(longitude, latitude):
    """WGS84 forward Transverse Mercator series for the supplied zone 51N.

    This audit only accepts points near Tomas Oppus, within 3 degrees of
    the central meridian. Its metre comparison allows 1 cm for series error.
    """
    if not (120 <= longitude <= 126 and 0 <= latitude <= 84):
        raise ValueError('Coordinates outside the supported UTM zone 51N audit area')
    phi = math.radians(latitude)
    lam = math.radians(longitude - 123)
    f = 1 / 298.257223563
    e2 = f * (2 - f)
    ep2 = e2 / (1 - e2)
    n = 6378137 / math.sqrt(1 - e2 * math.sin(phi) ** 2)
    t = math.tan(phi) ** 2
    c = ep2 * math.cos(phi) ** 2
    a = math.cos(phi) * lam
    m = 6378137 * (
        (1 - e2 / 4 - 3 * e2 ** 2 / 64 - 5 * e2 ** 3 / 256) * phi
        - (3 * e2 / 8 + 3 * e2 ** 2 / 32 + 45 * e2 ** 3 / 1024) * math.sin(2 * phi)
        + (15 * e2 ** 2 / 256 + 45 * e2 ** 3 / 1024) * math.sin(4 * phi)
        - 35 * e2 ** 3 / 3072 * math.sin(6 * phi)
    )
    x = 500000 + .9996 * n * (a + (1 - t + c) * a ** 3 / 6
        + (5 - 18 * t + t * t + 72 * c - 58 * ep2) * a ** 5 / 120)
    y = .9996 * (m + n * math.tan(phi) * (a * a / 2
        + (5 - t + 9 * c + 4 * c * c) * a ** 4 / 24
        + (61 - 58 * t + t * t + 600 * c - 330 * ep2) * a ** 6 / 720))
    return x, y


def records(path):
    raw = path.read_bytes()
    count, header, size = struct.unpack_from('<IHH', raw, 4)
    fields, offset = [], 1
    for i in range(32, header - 1, 32):
        if raw[i] == 13:
            break
        name = raw[i:i + 11].split(b'\0')[0].decode('ascii')
        length = raw[i + 16]
        fields.append((name, length, offset))
        offset += length
    rows = []
    for i in range(count):
        start = header + i * size
        if raw[start:start + 1] != b' ':
            raise ValueError(f'Deleted or invalid DBF record {i + 1}; manual review needed')
        rows.append({name: raw[start + offset:start + offset + length].decode('utf-8').strip()
                     for name, length, offset in fields})
    return rows


def in_ring(ring, point):
    x, y = point
    inside = False
    for (xi, yi), (xj, yj) in zip(ring, ring[-1:] + ring[:-1]):
        if (yi > y) != (yj > y) and x < (xj - xi) * (y - yi) / (yj - yi) + xi:
            inside = not inside
    return inside


def contains(geometry, point):
    polygons = [geometry['coordinates']] if geometry['type'] == 'Polygon' else geometry['coordinates']
    return any(in_ring(rings[0], point) and not any(in_ring(h, point) for h in rings[1:])
               for rings in polygons)


def audit(source, app_root):
    prj = source.with_suffix('.prj').read_text()
    required = ['WGS_1984_UTM_Zone_51N', '"Central_Meridian",123.0',
                '"Scale_Factor",0.9996', '"False_Easting",500000.0',
                '"False_Northing",0.0', '"Latitude_Of_Origin",0.0',
                '"WGS_1984",', '"Meter",1.0']
    if not all(value in prj for value in required):
        raise ValueError('Unexpected PRJ; this audit supports the supplied WGS84 UTM zone 51N only')
    if source.with_suffix('.cpg').read_text().strip().upper() != 'UTF-8':
        raise ValueError('Unexpected DBF encoding')
    raw = source.read_bytes()
    index = source.with_suffix('.shx').read_bytes()
    for data in [raw, index]:
        if struct.unpack_from('>I', data)[0] != 9994 or struct.unpack_from('>I', data, 24)[0] * 2 != len(data):
            raise ValueError('Invalid shapefile/index header or length')
        if struct.unpack_from('<II', data, 28) != (1000, 1):
            raise ValueError('Only Point shapefiles are supported')
    rows = records(source.with_suffix('.dbf'))
    points = json.loads((app_root / 'resources/gis/Household_Population_Bldg_Pts.geojson').read_text())['features']
    boundaries = json.loads((app_root / 'resources/gis/Boundary_Barangay.geojson').read_text())['features']
    boundary_codes = {str(f['properties']['BARANGAY_C']): f for f in boundaries}
    original_boundary_path = source.parent.parent / 'Boundaries/Boundary_Barangay.dbf'
    original_boundary_rows = records(original_boundary_path) if original_boundary_path.is_file() else []
    original_labels_available = bool(original_boundary_rows) and all(row.get('BARANGAY_C') for row in original_boundary_rows)
    mismatches, outside, source_counts = [], [], collections.Counter()
    maximum_projection_error = 0
    offset, record_number = 100, 0
    while offset < len(raw):
        number, words = struct.unpack_from('>II', raw, offset)
        record_number += 1
        shape, x, y = struct.unpack_from('<Idd', raw, offset + 8)
        index_entry = struct.unpack_from('>II', index, 100 + (record_number - 1) * 8)
        if number != record_number or shape != 1 or words != 10 or index_entry != (offset // 2, words):
            raise ValueError(f'SHP/SHX record mismatch at {record_number}')
        row = rows[record_number - 1]
        point = [float(row['LONGITUDE']), float(row['LATITUDE'])]
        code = row['BARANGAY_C']
        source_counts[code] += 1
        projected = utm51(*point)
        error = math.hypot(projected[0] - x, projected[1] - y)
        maximum_projection_error = max(maximum_projection_error, error)
        feature = points[record_number - 1] if record_number <= len(points) else None
        matches = feature and feature['geometry']['type'] == 'Point'
        if matches:
            matches = len(feature['geometry']['coordinates']) == 2 and all(abs(a - b) <= 1e-10 for a, b in zip(point, feature['geometry']['coordinates']))
            matches = matches and all(name in feature['properties'] and abs(float(value) - float(feature['properties'][name])) <= 1e-10
                                      for name, value in row.items())
        if not matches or error > .01:
            mismatches.append(record_number)
        if code not in boundary_codes or not contains(boundary_codes[code]['geometry'], point):
            outside.append(record_number)
        offset += 8 + words * 2
    counts_match = len(rows) == record_number == (len(index) - 100) // 8 == len(points)
    return {
        'source_crs': 'WGS84 / UTM zone 51N', 'map_crs': 'WGS84 longitude/latitude',
        'source_points': record_number, 'system_points': len(points),
        'counts_match': counts_match, 'mismatched_records': mismatches,
        'max_projection_error_metres': round(maximum_projection_error, 6),
        'source_points_outside_assigned_system_boundary': len(outside),
        'outside_boundary_record_numbers': outside,
        'original_boundary_labels_available': original_labels_available,
        'points_by_barangay': {boundary_codes[code]['properties']['Name']: count
                              for code, count in source_counts.items()},
        'passed': counts_match and not mismatches,
        'note': 'All source points are preserved. Boundary exceptions compare the supplied points against the system barangay boundaries. The original boundary DBF has no barangay codes for an independent per-barangay check. Review exceptions with the data owner; do not automatically relocate points or count buildings as registered households.'
    }


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('source', type=Path)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    result = audit(args.source, Path(__file__).resolve().parents[1])
    output = json.dumps(result, indent=2, ensure_ascii=False) + '\n'
    if args.output:
        args.output.write_text(output, encoding='utf-8')
    print(output)
    sys.exit(0 if result['passed'] else 1)
