from pathlib import Path
from html import escape
import json

from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import Paragraph
from reportlab.pdfbase.pdfmetrics import stringWidth


ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output' / 'architecture'
PDF = ROOT / 'output' / 'pdf'
OUT.mkdir(parents=True, exist_ok=True)
PDF.mkdir(parents=True, exist_ok=True)
W, H = 1440, 1690
NAVY, INK, MUTED, LINE = '#244563', '#182B3C', '#4B6072', '#9FB0BE'
ops = []


def box(x, y, w, h, fill='#FFFFFF', stroke=LINE, radius=12, dashed=False):
    ops.append(('box', (x, y, w, h, fill, stroke, radius, dashed)))


def text(x, y, label, size=24, bold=False, color=INK, anchor='start'):
    ops.append(('text', (x, y, label, size, bold, color, anchor)))


def path(points, both=False):
    ops.append(('path', (points, both)))


def card(x, y, w, h, title, lines, fill='#FFFFFF', dashed=False):
    box(x, y, w, h, fill=fill, dashed=dashed)
    text(x + w / 2, y + 39, title, 27, True, anchor='middle')
    for i, line in enumerate(lines):
        assert stringWidth(line, 'Helvetica', 23) <= w - 32, line
        text(x + w / 2, y + 76 + i * 30, line, 23, anchor='middle')


# Client tier
box(20, 20, 1400, 322, fill='#F5F8FB', stroke=NAVY, radius=16)
text(48, 66, 'CLIENT AND PRESENTATION TIER', 29, True, NAVY)
card(48, 96, 430, 132, 'Residents', ['Web browser or Android app', 'Requests, payments and concerns'])
card(505, 96, 430, 132, 'Barangay Secretaries', ['Web browser', 'Registry, RBI and local operations'])
card(962, 96, 430, 132, 'Municipal LGU Staff', ['Web browser', 'Approvals and municipal monitoring'])
text(720, 273, 'Browser UI: Blade, HTML, CSS, JavaScript and Leaflet', 24, anchor='middle')
text(720, 312, 'Staff offline capture: IndexedDB with authorized synchronization when online', 23, color=MUTED, anchor='middle')
path([(720, 362), (720, 418)], both=True)
text(748, 399, 'HTTPS requests and responses', 23, color=MUTED)

# Application tier
box(20, 438, 1400, 720, fill='#F7F9FB', stroke=NAVY, radius=16)
text(48, 484, 'APPLICATION TIER', 29, True, NAVY)
text(1392, 484, 'Laravel MVC application', 25, color=MUTED, anchor='end')
box(48, 510, 1344, 74, fill='#E5EDF5', stroke=NAVY)
text(720, 556, 'Traefik HTTPS routing  /  Apache web server and PHP', 28, True, anchor='middle')
path([(720, 584), (720, 620)], both=True)
box(48, 620, 1344, 90, fill='#FFFFFF', stroke=NAVY)
text(720, 657, 'Laravel routing and access control', 28, True, anchor='middle')
text(720, 692, 'Sessions and Sanctum tokens | Roles and approvals | Barangay and owner scope | Validation', 23, anchor='middle')
path([(720, 710), (720, 746)], both=True)
modules = [
    (48, 746, 'Registry and RBI', ['Households and resident records', 'Monthly forms and consolidation']),
    (505, 746, 'Requests and Payments', ['Certificates and fee tracking', 'Staff payment verification']),
    (962, 746, 'Resident Concerns', ['Submissions and follow-ups', 'Barangay staff handling']),
    (48, 910, 'GIS and Offline Sync', ['Saved coordinates and GIS layers', 'Authorized household syncing']),
    (505, 910, 'Reports and Forecasting', ['Population and migration reports', 'Verified forecasts and exports']),
    (962, 910, 'Database Assistant', ['Scoped English and Cebuano queries', 'FAQ and record-based answers']),
]
for x, y, title, lines in modules:
    card(x, y, 430, 138, title, lines)
path([(720, 1048), (720, 1080)], both=True)
box(48, 1080, 1344, 52, fill='#E5EDF5', stroke=NAVY)
text(720, 1115, 'Application services and Eloquent ORM', 26, True, anchor='middle')

# Shared data access branches; no client-to-database link.
path([(720, 1132), (720, 1190), (377, 1190), (377, 1295)], both=True)
path([(720, 1190), (1063, 1190), (1063, 1295)], both=True)
box(20, 1230, 1400, 270, fill='#F0F7F5', stroke='#557B72', radius=16)
text(48, 1274, 'DATA TIER', 29, True, '#315E52')
card(48, 1295, 658, 172, 'MySQL Database', [
    'Users, households and inhabitants',
    'RBI forms, migration and verifications',
    'Requests, payments, concerns and logs',
])
card(734, 1295, 658, 172, 'Application File Storage', [
    'Private receipts, signatures and attachments',
    'Public uploads, sessions and caches',
    'Persistent application storage volume',
])

# Supporting integrations use explicit endpoint labels to avoid implying that
# browser map-tile traffic passes through the database or the application.
box(20, 1540, 1400, 134, fill='#FFFFFF', stroke=LINE, radius=16)
text(48, 1580, 'SUPPORTING CONNECTIONS', 26, True, NAVY)
text(48, 1620, 'Application -> SMTP email', 24, True)
text(48, 1653, 'Account recovery messages', 23, color=MUTED)
text(507, 1620, 'Browser -> Map tiles', 24, True)
text(507, 1653, 'OpenStreetMap and Esri imagery', 23, color=MUTED)
box(952, 1595, 440, 67, fill='#FAFAFA', dashed=True)
text(1172, 1623, 'Assistant <-> Optional Ollama', 24, True, anchor='middle')
text(1172, 1651, 'Private language interpretation', 22, color=MUTED, anchor='middle')


def svg_output():
    content = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" role="img" aria-labelledby="title desc">',
               '<title id="title">RBIM System Architecture</title>',
               '<desc id="desc">Residents, barangay secretaries and municipal LGU staff use browser clients. Residents can also use the Android client. HTTPS connects clients to the Laravel application through Traefik and Apache. Application modules access MySQL and file storage through services. Supporting connections include SMTP, browser map tiles and optional private Ollama.</desc>',
               '<defs><marker id="end" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="8" markerHeight="8" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#557084"/></marker></defs>',
               '<rect width="100%" height="100%" fill="white"/>']
    # Data tier backgrounds precede arrows, so connection lines remain visible.
    background = [o for o in ops if o[0] == 'box' and o[1][0] == 20]
    foreground = [o for o in ops if o not in background]
    for kind, args in background + foreground:
        if kind == 'box':
            x, y, w, h, fill, stroke, radius, dashed = args
            dash = ' stroke-dasharray="8 5"' if dashed else ''
            content.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{radius}" fill="{fill}" stroke="{stroke}" stroke-width="2"{dash}/>')
        elif kind == 'text':
            x, y, label, size, bold, color, anchor = args
            content.append(f'<text x="{x}" y="{y}" font-family="Arial, Helvetica, sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="{color}" text-anchor="{anchor}">{escape(label)}</text>')
        else:
            points, both = args
            d = 'M ' + ' L '.join(f'{x} {y}' for x, y in points)
            start = ' marker-start="url(#end)"' if both else ''
            content.append(f'<path d="{d}" fill="none" stroke="#557084" stroke-width="2.5" marker-end="url(#end)"{start}/>')
    content.append('</svg>')
    return '\n'.join(content)


def draw_diagram(c):
    background = [o for o in ops if o[0] == 'box' and o[1][0] == 20]
    foreground = [o for o in ops if o not in background]
    for kind, args in background + foreground:
        if kind == 'box':
            x, y, w, h, fill, stroke, radius, dashed = args
            c.setFillColor(HexColor(fill))
            c.setStrokeColor(HexColor(stroke))
            c.setLineWidth(2)
            c.setDash(8, 5) if dashed else c.setDash()
            c.roundRect(x, H - y - h, w, h, radius, fill=1, stroke=1)
            c.setDash()
        elif kind == 'text':
            x, y, label, size, bold, color, anchor = args
            c.setFillColor(HexColor(color))
            c.setFont('Helvetica-Bold' if bold else 'Helvetica', size)
            fn = {'start': c.drawString, 'middle': c.drawCentredString, 'end': c.drawRightString}[anchor]
            fn(x, H - y, label)
        else:
            points, both = args
            c.setStrokeColor(HexColor('#557084'))
            c.setFillColor(HexColor('#557084'))
            c.setLineWidth(2.5)
            p = c.beginPath()
            p.moveTo(points[0][0], H - points[0][1])
            for x, y in points[1:]:
                p.lineTo(x, H - y)
            c.drawPath(p)
            def arrow(a, b):
                import math
                dx, dy = b[0] - a[0], b[1] - a[1]
                norm = math.hypot(dx, dy)
                ux, uy = dx / norm, dy / norm
                tipx, tipy = b[0], H - b[1]
                triangle = c.beginPath()
                triangle.moveTo(tipx, tipy)
                triangle.lineTo(tipx - 15 * ux + 6 * uy, tipy + 15 * uy + 6 * ux)
                triangle.lineTo(tipx - 15 * ux - 6 * uy, tipy + 15 * uy - 6 * ux)
                triangle.close()
                c.drawPath(triangle, fill=1, stroke=0)
            arrow(points[-2], points[-1])
            if both:
                arrow(points[1], points[0])


(OUT / 'rbim-system-architecture.svg').write_text(svg_output(), encoding='utf-8')

paragraphs = [
    ('Architecture overview',
     'The developed RBIM system follows a three-tier architecture comprising the presentation tier, the application tier, and the data tier. Its Laravel application uses the Model-View-Controller pattern to separate screen rendering, request handling, and record access. Residents, barangay secretaries, and municipal LGU staff use the same central application with different permissions. The architecture supports resident services and barangay record maintenance while providing authorized municipal monitoring.'),
    ('Presentation and access',
     'The browser interface uses Blade templates, HTML, CSS, and JavaScript. Residents submit document requests, provide payment evidence, and follow up on concerns. Barangay secretaries maintain their assigned barangay records and process service transactions. Municipal LGU staff manage authorized approvals and view municipal reports; access to sensitive records remains subject to module-specific permissions. A native Android client provides resident workflows through the Laravel HTTPS API using Sanctum tokens. Browser sessions use session authentication.'),
    ('Application processing',
     'Routes and middleware check authentication, account approval, role, and barangay or record ownership before protected operations. Controllers validate submitted information and call application services. The main modules cover household and resident records, monthly RBI forms and consolidation, document requests and payment review, concern management, GIS mapping, offline synchronization, population reporting, migration forecasting, and the English and Cebuano database assistant. Eloquent models handle relational data access. Authorized download routes control access to private files.'),
    ('Forecasting and supporting services',
     'The forecasting engine runs in PHP and uses consecutive, staff-verified monthly arrival and departure counts. It compares candidate time-series methods, evaluates held-out predictions using mean absolute error, and calculates net migration as predicted arrivals minus predicted departures. Forecasts remain unavailable when verified history is insufficient. Optional Ollama support interprets assistant questions on a private connection; it does not run the numerical forecast. SMTP supports account recovery email, while the browser retrieves OpenStreetMap and Esri map tiles. GCash receipts are evidence for staff review; uploading a receipt does not execute a payment.'),
    ('Data persistence and offline operation',
     'MySQL stores accounts, households, inhabitants, RBI submissions, migration events and verification periods, document transactions, payment records, concerns, and activity history. Application storage holds private attachments, signatures, receipts, approved public files, sessions, and caches. The browser household capture workflow stores queued records in IndexedDB while offline and synchronizes them through an authenticated staff endpoint when connectivity returns. Server validation remains authoritative. The Android resident application requires connectivity and does not use this offline submission workflow.'),
    ('Deployment design',
     'The repository defines a VPS deployment managed through Dokploy. Traefik routes HTTPS traffic to an Apache and PHP application container, which connects to a separate MySQL container on a Docker network. Persistent volumes retain database files and application storage across container replacement. GitHub supplies application source for a reviewed build and redeployment. Schema migrations update the hosted database separately unless startup migrations are explicitly enabled. This deployment design describes the supplied configuration; the active online release and server settings require separate verification.'),
]
(OUT / 'architecture-explanation.txt').write_text(
    'Architectural Design of the Developed System\n\n' + '\n\n'.join(h + '\n' + p for h, p in paragraphs), encoding='utf-8')

pdf_path = PDF / 'architectural-design-developed-system.pdf'
c = canvas.Canvas(str(pdf_path), pagesize=A4)
c.setTitle('Architectural Design of the Developed System')
c.setAuthor('')
c.setSubject('RBIM system architecture and component responsibilities')
pw, ph = A4
margin = 43
c.setFont('Helvetica-Bold', 17)
c.setFillColor(HexColor(INK))
c.drawString(margin, ph - 50, 'Architectural Design of the Developed System')
c.setFont('Helvetica', 10)
c.setFillColor(HexColor(MUTED))
c.drawString(margin, ph - 69, 'RBIM  |  Municipality of Tomas Oppus')
scale = (pw - 2 * margin) / W
bottom = 92
c.saveState()
c.translate(margin, bottom)
c.scale(scale, scale)
draw_diagram(c)
c.restoreState()
c.setFillColor(HexColor(INK))
c.setFont('Helvetica-Oblique', 9)
c.drawCentredString(pw / 2, 69, 'Figure 1. Architectural design of the developed RBIM system.')
c.setFont('Helvetica', 8)
c.setFillColor(HexColor(MUTED))
c.drawCentredString(pw / 2, 51, 'RBI = Registry of Barangay Inhabitants. Dashed box = optional service.')
c.drawRightString(pw - margin, 29, '1')
c.showPage()

c.setFont('Helvetica-Bold', 17)
c.setFillColor(HexColor(INK))
c.drawString(margin, ph - 50, 'Architecture Description')
y = ph - 79
body_style = ParagraphStyle('Body', fontName='Helvetica', fontSize=10.3, leading=14.3, textColor=HexColor(INK), spaceAfter=0)
for heading, body in paragraphs:
    c.setFont('Helvetica-Bold', 11)
    c.setFillColor(HexColor(INK))
    c.drawString(margin, y, heading)
    y -= 9
    paragraph = Paragraph(escape(body), body_style)
    w, h = paragraph.wrap(pw - 2 * margin, ph)
    paragraph.drawOn(c, margin, y - h)
    y -= h + 20
assert y >= 50, f'Explanation overflows page: {y}'
c.setFont('Helvetica', 8)
c.setFillColor(HexColor(MUTED))
c.drawRightString(pw - margin, 29, '2')
c.save()
print(json.dumps({'pdf': str(pdf_path), 'svg': str(OUT / 'rbim-system-architecture.svg'), 'description': str(OUT / 'architecture-explanation.txt'), 'page_two_bottom': y}, indent=2))
