"""Read-only source review. Cell text remains in private storage, not the public report."""
import argparse
import hashlib
import json
import re
from pathlib import Path
import xml.etree.ElementTree as ET
from zipfile import ZipFile

NS = '{http://schemas.openxmlformats.org/spreadsheetml/2006/main}'
REL = '{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id'
MOVEMENT = re.compile(r'\b(?:arriv\w*|in[ -]?migra\w*|out[ -]?migra\w*|mov(?:e|ed|ing)\s+(?:in|out)|transfer(?:red|ed)?)\b', re.I)
YEAR = re.compile(r'\b(?:19|20)\d{2}\b')
DATE_HEADER = re.compile(r'(?:date|month|year).*(?:arrival|departure|transfer|migration)|(?:arrival|departure|transfer|migration).*(?:date|month|year)', re.I)


def audit(path):
    digest = hashlib.sha256(path.read_bytes()).hexdigest()
    result = {'file': path.name, 'sha256': digest, 'sheets': [], 'dated_movement_text': [], 'movement_date_headers': [], 'new_headers': []}
    with ZipFile(path) as z:
        shared = []
        if 'xl/sharedStrings.xml' in z.namelist():
            with z.open('xl/sharedStrings.xml') as stream:
                for _, node in ET.iterparse(stream, events=['end']):
                    if node.tag == NS+'si':
                        shared.append(''.join(x.text or '' for x in node.iter(NS+'t')))
                        node.clear()
        rels = {r.attrib['Id']: r.attrib['Target'] for r in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
        book = ET.fromstring(z.read('xl/workbook.xml'))
        for sheet in book.find(NS+'sheets'):
            name = sheet.attrib['name']
            target = rels[sheet.attrib[REL]]
            target = target.lstrip('/') if target.startswith('/') else 'xl/'+target
            target = target.replace('xl/../', '')
            count = 0
            with z.open(target) as stream:
                for _, row in ET.iterparse(stream, events=['end']):
                    if row.tag != NS+'row':
                        continue
                    for cell in row.findall(NS+'c'):
                        value = cell.findtext(NS+'v', '')
                        if cell.attrib.get('t') == 's' and value:
                            value = shared[int(value)]
                        elif cell.attrib.get('t') == 'inlineStr':
                            value = ''.join(x.text or '' for x in cell.iter(NS+'t'))
                        if not value.strip():
                            continue
                        count += 1
                        ref = {'sheet': name, 'cell': cell.attrib['r'], 'text': value.strip()}
                        if MOVEMENT.search(value) and YEAR.search(value):
                            result['dated_movement_text'].append(ref)
                        if DATE_HEADER.search(value):
                            result['movement_date_headers'].append(ref)
                        if re.search(r'NEW INHABITANT|MONTH SUBMITTED|REPORTING (?:MONTH|YEAR)', value, re.I):
                            result['new_headers'].append(ref)
                    row.clear()
            result['sheets'].append({'name': name, 'nonempty_cells': count})
    if digest != hashlib.sha256(path.read_bytes()).hexdigest():
        raise RuntimeError('Workbook changed during review')
    return result


def main():
    p = argparse.ArgumentParser()
    p.add_argument('source', type=Path)
    p.add_argument('output', type=Path)
    a = p.parse_args()
    if a.output.exists():
        raise FileExistsError('Use a fresh private review directory')
    a.output.mkdir(parents=True)
    inventory = json.loads((a.source/'archive-inventory.json').read_text(encoding='utf-8-sig'))
    results = []
    for item in inventory:
        result = audit(a.source/Path(item['file']).name)
        results.append(result)
        print(json.dumps({'file': item['file'], 'sheets': len(result['sheets']),
                          'dated_movement_text': len(result['dated_movement_text']), 'movement_date_headers': len(result['movement_date_headers'])}), flush=True)
    payload = {'workbooks_reviewed': len(results), 'sheets_reviewed': sum(len(r['sheets']) for r in results),
               'workbooks': results, 'source_files_changed': False,
               'note': 'Text matches are review candidates, not confirmed events. Header years, document titles and birth dates do not establish movement dates.'}
    (a.output/'workbook-review.json').write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding='utf-8')


if __name__ == '__main__':
    main()
