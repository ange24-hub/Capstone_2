from pathlib import Path
from html import escape
import json
import math

from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.colors import HexColor
from reportlab.pdfbase.pdfmetrics import stringWidth

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output' / 'architecture'
PDF = ROOT / 'output' / 'pdf'
OUT.mkdir(parents=True, exist_ok=True)
PDF.mkdir(parents=True, exist_ok=True)
W, H = 1920, 1315
ops = []
INK = '#213140'
COLORS = ['#135BAC', '#287C3B', '#6441AC', '#CD7310', '#008691', '#B18A0B']
TINTS = ['#F1F7FE', '#F2FAF3', '#F6F3FC', '#FFF8ED', '#EFFAFA', '#FFFBEE']


def rect(x, y, w, h, fill, stroke='none', radius=9, dashed=False, sw=1.6):
    ops.append(('rect', (x, y, w, h, fill, stroke, radius, dashed, sw)))


def text(x, y, value, size=23, bold=False, color=INK, anchor='middle'):
    ops.append(('text', (x, y, value, size, bold, color, anchor)))


def line(points, color, width=2, dashed=False, arrows=False):
    ops.append(('line', (points, color, width, dashed, arrows)))


def circle(x, y, r, fill='none', stroke=INK, width=2):
    ops.append(('circle', (x, y, r, fill, stroke, width)))


def ellipse(x, y, rx, ry, fill='none', stroke=INK, width=2):
    ops.append(('ellipse', (x, y, rx, ry, fill, stroke, width)))


def icon(name, cx, cy, color, s=1):
    def p(points, close=False):
        pts = [(cx + x * s, cy + y * s) for x, y in points]
        if close:
            pts.append(pts[0])
        line(pts, color, 3 * s)
    def r(x, y, w, h):
        rect(cx + x * s, cy + y * s, w * s, h * s, 'none', color, 3 * s, sw=3 * s)
    def c(x, y, radius):
        circle(cx + x * s, cy + y * s, radius * s, 'none', color, 3 * s)
    if name == 'user':
        c(0, -11, 9)
        p([(-20, 23), (-18, 10), (-9, 3), (9, 3), (18, 10), (20, 23)], True)
    elif name == 'screen':
        r(-25, -21, 50, 34)
        p([(0, 13), (0, 24), (-14, 24), (14, 24)])
        p([(-15, 2), (-15, -5), (-5, -5), (-5, 2), (5, 2), (5, -12), (15, -12), (15, 2)])
    elif name == 'shield':
        p([(0, -25), (22, -15), (19, 8), (10, 20), (0, 27), (-10, 20), (-19, 8), (-22, -15)], True)
        p([(-10, 0), (-2, 8), (12, -10)])
    elif name == 'people':
        c(-10, -10, 8)
        c(12, -13, 7)
        p([(-28, 23), (-25, 8), (-15, 2), (-5, 2), (5, 8), (8, 23)])
        p([(6, 1), (17, 1), (27, 8), (28, 23)])
    elif name == 'document':
        p([(-18, -25), (7, -25), (21, -11), (21, 25), (-18, 25)], True)
        p([(7, -25), (7, -11), (21, -11)])
        for y in [0, 10, 19]:
            p([(-10, y), (12, y)])
    elif name == 'payment':
        r(-26, -17, 52, 36)
        p([(-26, -6), (26, -6)])
        p([(-17, 8), (-5, 8)])
        c(16, 16, 12)
        p([(10, 16), (15, 21), (23, 11)])
    elif name == 'chat':
        p([(-26, -21), (26, -21), (26, 12), (7, 12), (-7, 25), (-7, 12), (-26, 12)], True)
        for x in [-13, 0, 13]:
            c(x, -5, 2)
    elif name == 'map':
        p([(-27, -19), (-10, -25), (10, -19), (27, -25), (27, 20), (10, 26), (-10, 20), (-27, 26)], True)
        p([(-10, -25), (-10, 20)])
        p([(10, -19), (10, 26)])
        c(3, -1, 7)
        p([(-3, 5), (3, 15), (9, 5)])
    elif name == 'chart':
        p([(-26, -25), (-26, 24), (27, 24)])
        p([(-18, 10), (-4, 0), (6, 7), (24, -15)])
        p([(12, -15), (24, -15), (24, -3)])
    elif name == 'history':
        c(2, 0, 23)
        p([(2, -13), (2, 1), (15, 8)])
        p([(-30, -14), (-20, -14), (-20, -25)])
    elif name == 'checks':
        for y in [-18, 0, 18]:
            p([(-25, y), (-20, y + 5), (-12, y - 6)])
            p([(-4, y), (25, y)])
    elif name == 'model':
        for x, y in [(-22, -17), (22, -17), (0, 0), (-22, 20), (22, 20)]:
            c(x, y, 6)
        for x, y in [(-17, -13), (17, -13), (-17, 15), (17, 15)]:
            p([(x, y), (0, 0)])
    elif name == 'calendar':
        r(-25, -21, 50, 45)
        p([(-25, -7), (25, -7)])
        p([(-13, -28), (-13, -15)])
        p([(13, -28), (13, -15)])
        for x in [-13, 0, 13]:
            for y in [3, 14]:
                c(x, y, 1.8)
    elif name == 'mail':
        r(-28, -19, 56, 40)
        p([(-28, -19), (0, 3), (28, -19)])
        p([(-28, 21), (-9, 2)])
        p([(28, 21), (9, 2)])
    elif name == 'database':
        ellipse(cx, cy - 18 * s, 25 * s, 9 * s, 'none', color, 3 * s)
        p([(-25, -18), (-25, 19)])
        p([(25, -18), (25, 19)])
        for y in [0, 19]:
            ellipse(cx, cy + y * s, 25 * s, 9 * s, 'none', color, 3 * s)
    elif name == 'folder':
        p([(-28, -15), (-6, -15), (0, -23), (26, -23), (26, 22), (-28, 22)], True)
        p([(-28, -6), (26, -6)])
    elif name == 'server':
        for y in [-22, 0, 22]:
            r(-28, y - 7, 56, 16)
            c(-18, y, 1.5)
            p([(2, y), (18, y)])
    elif name == 'code':
        p([(-12, -19), (-28, 0), (-12, 19)])
        p([(12, -19), (28, 0), (12, 19)])
        p([(5, -25), (-5, 25)])
    elif name == 'containers':
        for x, y in [(-23, -18), (0, -18), (23, -18), (-23, 7), (0, 7), (23, 7)]:
            r(x - 9, y - 9, 18, 18)
    elif name == 'branch':
        c(-14, -20, 6)
        c(-14, 21, 6)
        c(20, -17, 6)
        p([(-14, -14), (-14, 15)])
        p([(-14, 7), (6, 7), (20, -4), (20, -11)])
    elif name == 'globe':
        c(0, 0, 26)
        ellipse(cx, cy, 12 * s, 26 * s, 'none', color, 2.5 * s)
        p([(-26, 0), (26, 0)])
        p([(-22, -13), (22, -13)])
        p([(-22, 13), (22, 13)])


def band(number, y, h, labels):
    color, tint = COLORS[number - 1], TINTS[number - 1]
    rect(45, y, 1830, h, tint, color, 11)
    rect(45, y, 75, h, color, color, 10)
    rect(105, y + 1, 15, h - 2, color, color, 0)
    circle(82, y + h / 2, 21, '#FFFFFF', '#FFFFFF', 0)
    text(82, y + h / 2 + 9, str(number), 28, True, color)
    line([(335, y), (335, y + h)], color, 1.2)
    top = y + h / 2 - (len(labels) - 1) * 15 + 9
    for i, value in enumerate(labels):
        text(225, top + 30 * i, value, 23, True, color)


text(960, 43, 'Architectural Design of the Developed System', 34, True)
text(960, 75, 'RBIM | Municipality of Tomas Oppus', 23, color='#526474')

band(1, 100, 170, ['PRESENTATION', 'LAYER'])
for x, name, role, sub in [
    (390, 'user', 'Resident Portal', 'Web browser or Android app'),
    (875, 'screen', 'Barangay Secretary', 'Barangay dashboard'),
    (1360, 'screen', 'Municipal LGU', 'Municipal dashboard'),
]:
    icon(name, x + 30, 154, COLORS[0], .85)
    text(x + 78, 145, role, 27, True, anchor='start')
    text(x + 78, 176, sub, 22, anchor='start')
rect(370, 214, 1465, 39, '#E5EFFB', '#A7C7E9', 8)
text(1102, 240, 'Laravel Blade | HTML and CSS | JavaScript | Leaflet | Resident Android client through HTTPS API', 23)
line([(1100, 274), (1100, 291)], COLORS[0], 2.4, arrows=True)
text(1127, 287, 'HTTPS requests and responses', 19, color=COLORS[0], anchor='start')

band(2, 300, 186, ['APPLICATION', 'LAYER'])
text(1102, 334, 'Laravel Backend', 29, True, COLORS[1])
modules = [
    ('shield', ['Access', 'Control']), ('people', ['Residents', 'and RBI']),
    ('document', ['Document', 'Requests']), ('payment', ['Payment', 'Review']),
    ('chat', ['Concern', 'Handling']), ('map', ['GIS and', 'Offline Sync']),
    ('chart', ['Reports and', 'Exports']), ('history', ['Activity', 'History']),
]
for i, (name, labels) in enumerate(modules):
    x = 370 + i * 185
    rect(x, 348, 170, 100, '#FFFFFF', '#BCD6C2', 8)
    icon(name, x + 27, 384, COLORS[1], .56)
    for j, label in enumerate(labels):
        assert stringWidth(label, 'Helvetica-Bold', 19) <= 112, label
        text(x + 50, 379 + j * 26, label, 19, True, anchor='start')
text(1102, 473, 'Controllers and services | Sessions and Sanctum tokens | Roles, approvals and scoped record access', 22)
line([(1100, 490), (1100, 510)], COLORS[2], 2.4, arrows=True)
text(1127, 505, 'Verified monthly counts / forecast results', 19, color=COLORS[2], anchor='start')

band(3, 520, 170, ['ANALYTICS AND', 'FORECASTING', 'LAYER'])
text(1102, 553, 'Native PHP Forecasting Engine', 29, True, COLORS[2])
pipeline = [
    ('checks', ['Verified monthly', 'history']), ('model', ['Time-series', 'model candidates']),
    ('calendar', ['Chronological', 'validation']), ('chart', ['MAE and baseline', 'comparison']),
    ('people', ['Arrivals, departures', 'and net migration']),
]
for i, (name, labels) in enumerate(pipeline):
    x = 370 + i * 296
    rect(x, 570, 277, 72, '#FFFFFF', '#CBBCE7', 8)
    icon(name, x + 29, 604, COLORS[2], .57)
    for j, label in enumerate(labels):
        text(x + 63, 597 + 27 * j, label, 21, True, anchor='start')
    if i < 4:
        line([(x + 280, 606), (x + 291, 606)], COLORS[2], 1.8, arrows='end')
text(1102, 674, 'Last-month baseline | Moving average | Exponential smoothing | Holt trend | Verified history required', 21)

band(4, 725, 140, ['SUPPORTING', 'SERVICES LAYER'])
supports = [
    ('mail', 'SMTP Email', 'Account recovery messages'),
    ('globe', 'Map Tile Services', 'Browser loads OSM / Esri tiles'),
    ('chat', 'Database Assistant', 'Scoped English / Cebuano answers'),
    ('model', 'Optional Ollama', 'Private question interpretation'),
]
for i, (name, title, sub) in enumerate(supports):
    x = 370 + i * 370
    rect(x, 745, 352, 98, '#FFFFFF', '#DFBC89', 8, dashed=(i == 3))
    icon(name, x + 30, 782, COLORS[3], .6)
    text(x + 64, 778, title, 24, True, anchor='start')
    text(x + 176, 823, sub, 20)

band(5, 900, 164, ['DATA', 'LAYER'])
text(1102, 934, 'MySQL Database and Persistent File Storage', 29, True, COLORS[4])
data = [
    ('user', ['Accounts and', 'access records']), ('people', ['Households', 'and RBI records']),
    ('calendar', ['Migration and', 'verified periods']), ('payment', ['Requests and', 'payment records']),
    ('chat', ['Concerns and', 'activity logs']), ('folder', ['Receipts, signatures', 'and attachments']),
]
for i, (name, labels) in enumerate(data):
    cx = 488 + i * 246
    icon(name, cx, 978, COLORS[4], .66)
    for j, label in enumerate(labels):
        text(cx, 1021 + j * 25, label, 21)

# Backend-to-data path bypasses analytical and supporting services.
line([(370, 465), (352, 465), (352, 882), (1100, 882), (1100, 896)], COLORS[4], 2.4, arrows=True)
text(1127, 888, 'Eloquent reads and writes / protected file access', 18, color=COLORS[4], anchor='start')
# Supporting-service connections are separate from the forecast pipeline.
line([(1879, 398), (1896, 398), (1896, 795), (1879, 795)], COLORS[3], 2, dashed=True, arrows=True)

band(6, 1099, 154, ['DEPLOYMENT', 'LAYER'])
text(1102, 1131, 'Configured VPS Deployment', 29, True, COLORS[5])
infra = [
    ('branch', 'GitHub Source'), ('code', 'Dokploy Builds'), ('containers', 'Docker Containers'),
    ('shield', 'Traefik HTTPS'), ('server', 'Apache and PHP'), ('database', 'Persistent Volumes'),
]
for i, (name, label) in enumerate(infra):
    cx = 488 + i * 246
    icon(name, cx, 1170, COLORS[5], .65)
    text(cx, 1211, label, 21, True)
text(1102, 1238, 'Hostinger VPS configuration | App and MySQL containers | Private networks | Optional Ollama container', 20)
text(960, 1287, 'Figure 1. Architectural design of the developed RBIM system.', 24, True)
text(960, 1310, 'Dashed path: supporting-service connection. Dashed box: optional component. Deployment hosts the application and data tiers.', 18, color='#526474')


def svg():
    out = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" role="img" aria-labelledby="title desc">',
           '<title id="title">Architectural Design of the Developed RBIM System</title>',
           '<desc id="desc">Six colored layers show presentation clients, Laravel application modules, native PHP forecasting, supporting services, MySQL and persistent files, and configured VPS deployment. HTTPS connects clients to the application. A separate Eloquent path links the application to storage. Optional Ollama is shown with a dashed border.</desc>',
           '<rect width="100%" height="100%" fill="white"/>']
    for index, (kind, a) in enumerate(ops):
        if kind == 'rect':
            x, y, w, h, fill, stroke, radius, dashed, sw = a
            ds = ' stroke-dasharray="7 5"' if dashed else ''
            out.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{radius}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"{ds}/>')
        elif kind == 'text':
            x, y, value, size, bold, color, anchor = a
            out.append(f'<text x="{x}" y="{y}" font-family="Arial, Helvetica, sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="{color}" text-anchor="{anchor}">{escape(value)}</text>')
        elif kind in ['circle', 'ellipse']:
            if kind == 'circle':
                x, y, r, fill, stroke, sw = a
                out.append(f'<circle cx="{x}" cy="{y}" r="{r}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')
            else:
                x, y, rx, ry, fill, stroke, sw = a
                out.append(f'<ellipse cx="{x}" cy="{y}" rx="{rx}" ry="{ry}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')
        else:
            points, color, sw, dashed, arrows = a
            ds = ' stroke-dasharray="7 5"' if dashed else ''
            d = 'M ' + ' L '.join(f'{x} {y}' for x, y in points)
            out.append(f'<path d="{d}" fill="none" stroke="{color}" stroke-width="{sw}" stroke-linejoin="round" stroke-linecap="round"{ds}/>')
            if arrows:
                ends = [(points[-2], points[-1])]
                if arrows is True:
                    ends.append((points[1], points[0]))
                for previous, tip in ends:
                    dx, dy = tip[0] - previous[0], tip[1] - previous[1]
                    norm = math.hypot(dx, dy)
                    ux, uy = dx / norm, dy / norm
                    poly = [tip, (tip[0] - 7 * ux + 4 * uy, tip[1] - 7 * uy - 4 * ux), (tip[0] - 7 * ux - 4 * uy, tip[1] - 7 * uy + 4 * ux)]
                    out.append(f'<polygon points="{" ".join(f"{x},{y}" for x,y in poly)}" fill="{color}"/>')
    out.append('</svg>')
    return '\n'.join(out)


def draw(c):
    def fill(v):
        if v != 'none': c.setFillColor(HexColor(v))
    def stroke(v):
        if v != 'none': c.setStrokeColor(HexColor(v))
    for kind, a in ops:
        if kind == 'rect':
            x, y, w, h, f, s, radius, dashed, sw = a
            fill(f); stroke(s); c.setLineWidth(sw)
            c.setDash(7, 5) if dashed else c.setDash()
            c.roundRect(x, H-y-h, w, h, radius, fill=f!='none', stroke=s!='none')
            c.setDash()
        elif kind == 'text':
            x, y, value, size, bold, color, anchor = a
            fill(color); c.setFont('Helvetica-Bold' if bold else 'Helvetica', size)
            {'start':c.drawString, 'middle':c.drawCentredString, 'end':c.drawRightString}[anchor](x,H-y,value)
        elif kind == 'circle':
            x,y,r,f,s,sw = a
            fill(f); stroke(s); c.setLineWidth(sw)
            c.circle(x,H-y,r,fill=f!='none',stroke=s!='none')
        elif kind == 'ellipse':
            x,y,rx,ry,f,s,sw = a
            fill(f); stroke(s); c.setLineWidth(sw)
            c.ellipse(x-rx,H-y-ry,x+rx,H-y+ry,fill=f!='none',stroke=s!='none')
        else:
            points,color,sw,dashed,arrows = a
            stroke(color); fill(color); c.setLineWidth(sw)
            c.setDash(7,5) if dashed else c.setDash()
            p=c.beginPath(); p.moveTo(points[0][0],H-points[0][1])
            for x,y in points[1:]: p.lineTo(x,H-y)
            c.drawPath(p)
            c.setDash()
            if arrows:
                ends = [(points[-2], points[-1])]
                if arrows is True:
                    ends.append((points[1], points[0]))
                for previous,tip in ends:
                    dx,dy=tip[0]-previous[0],tip[1]-previous[1]
                    norm=math.hypot(dx,dy); ux,uy=dx/norm,dy/norm
                    poly=[tip,(tip[0]-7*ux+4*uy,tip[1]-7*uy-4*ux),(tip[0]-7*ux-4*uy,tip[1]-7*uy+4*ux)]
                    p=c.beginPath(); p.moveTo(poly[0][0],H-poly[0][1])
                    for x,y in poly[1:]: p.lineTo(x,H-y)
                    p.close(); c.drawPath(p,fill=1,stroke=0)


svg_path = OUT / 'rbim-layered-architectural-design.svg'
svg_path.write_text(svg(),encoding='utf-8')
pdf_path=PDF/'rbim-layered-architectural-design.pdf'
c=canvas.Canvas(str(pdf_path),pagesize=landscape(A4))
c.setTitle('Architectural Design of the Developed RBIM System')
c.setAuthor('')
pw,ph=landscape(A4)
scale=min((pw-32)/W,(ph-28)/H)
c.translate((pw-W*scale)/2,(ph-H*scale)/2)
c.scale(scale,scale)
draw(c)
c.save()
print(json.dumps({'svg':str(svg_path),'pdf':str(pdf_path)},indent=2))
