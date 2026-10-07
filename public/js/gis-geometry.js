/* Point-in-polygon checks for the supplied GeoJSON hazard overlays. */
(() => {
    'use strict';

    function insideRing(ring, point) {
        const [x, y] = point;
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const [xi, yi] = ring[i];
            const [xj, yj] = ring[j];
            const cross = (x - xi) * (yj - yi) - (y - yi) * (xj - xi);
            const tolerance = 1e-10;
            if (Math.abs(cross) <= tolerance * Math.max(Math.abs(xj - xi), Math.abs(yj - yi))
                && x >= Math.min(xi, xj) - tolerance && x <= Math.max(xi, xj) + tolerance
                && y >= Math.min(yi, yj) - tolerance && y <= Math.max(yi, yj) + tolerance) return true;
            if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) inside = !inside;
        }
        return inside;
    }

    function containsPoint(geometry, point) {
        if (!geometry || !Array.isArray(point)) return false;
        const polygons = geometry.type === 'Polygon' ? [geometry.coordinates]
            : geometry.type === 'MultiPolygon' ? geometry.coordinates : [];
        return polygons.some(rings => rings.length > 0 && insideRing(rings[0], point)
            && !rings.slice(1).some(hole => insideRing(hole, point)));
    }

    const api = { containsPoint };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (typeof window !== 'undefined') window.GisGeometry = api;
})();
