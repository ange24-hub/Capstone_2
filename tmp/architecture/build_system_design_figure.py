"""Build a manuscript system-design figure and its accompanying explanation."""
from pathlib import Path
from html import escape
import json

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/architecture'
OUT.mkdir(parents=True, exist_ok=True)
W, H = 1800, 1520
nodes, edges, texts = [], [], []

def box(x, y, w, h, label='', fill='#EFF5FC', stroke='#668BB5', size=29):
    nodes.append(dict(x=x, y=y, w=w, h=h, text=label, shape='process',
                      fill=fill, stroke=stroke, size=size))

def line(points):
    edges.append(dict(points=points, label='', lx=0, ly=0, both=True))

def text(x, y, label, size=25, bold=False):
    texts.append(dict(x=x, y=y, text=label, size=size, bold=bold))

text(900, 60, 'SYSTEM DESIGN', 45, True)
text(900, 112, 'Smart Barangay Information System for Population and Governance Management', 28)
text(900, 185, 'PRESENTATION LAYER', 25, True)
box(80, 225, 500, 125, 'Residents\nWeb portal / Android application')
box(650, 225, 500, 125, 'Barangay Secretaries\nWeb dashboard')
box(1220, 225, 500, 125, 'Municipal LGU Staff\nWeb dashboard')
line([(330,350), (330,400), (550,400), (550,450)])
line([(900,350), (900,450)])
line([(1470,350), (1470,400), (1250,400), (1250,450)])
box(300, 450, 1200, 120,
    'Authentication and Access Control\nAccount approval, user role, barangay scope and record ownership',
    '#FFF8E8', '#BAA16A', 29)
text(1220, 625, 'Authorized requests / permitted responses', 23)
line([(900,570), (900,680)])
box(80, 680, 1640, 445, '', '#F7FAF9', '#6D9C8B')
text(900, 730, 'APPLICATION LAYER - LARAVEL', 32, True)
text(900, 778, 'Controllers validate requests; services apply the system rules', 26)
modules = [
    'Accounts and\nApprovals',
    'Resident, Household\nand RBI Records',
    'Document Requests\nand Payment Review',
    'Resident Concerns\nand Replies',
    'Migration Monitoring\nand PHP Forecasting',
    'GIS Maps and\nOffline Household Sync',
    'Reports, Exports\nand Activity History',
    'Database Assistant\nEnglish / Cebuano',
]
for i, label in enumerate(modules):
    box(115 + (i % 4) * 405, 830 + (i // 4) * 145, 355, 110,
        label, '#ECF6F1', '#80A995', 27)
line([(480,1125), (480,1260)])
line([(1320,1125), (1320,1260)])
text(660, 1190, 'Read / write', 23)
text(1510, 1190, 'Store / retrieve', 23)
text(900, 1220, 'DATA LAYER', 25, True)
box(180, 1260, 600, 125, 'MySQL Database\nAccounts, registry and transaction records', '#F2F5F8', '#899BAC', 28)
box(1020, 1260, 600, 125, 'Protected File Storage\nReceipts, signatures and attachments', '#F2F5F8', '#899BAC', 28)
text(900, 1440, 'Double-headed arrows indicate requests / responses or storage access.', 24)
text(900, 1480, 'Modules are available functions, not a required sequence of steps.', 24)

def svg_text(x, y, value, size, bold=False):
    lines = value.split('\n')
    return ''.join(
        f'<text x="{x}" y="{y+(i-(len(lines)-1)/2)*size*1.22}" '
        f'text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" '
        f'font-size="{size}" font-weight="{700 if bold else 400}" fill="#243447">'
        f'{escape(v)}</text>' for i, v in enumerate(lines))

svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" role="img" aria-labelledby="title desc">',
       '<title id="title">System design of SB-ISPGM</title>',
       '<desc id="desc">Resident, barangay and municipal interfaces connect through access controls to Laravel modules, a MySQL database and protected file storage.</desc>',
       '<rect width="100%" height="100%" fill="white"/>',
       '<defs><marker id="a" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto-start-reverse"><path d="M0,1 L9,5 L0,9" fill="none" stroke="#617184" stroke-width="1.6"/></marker></defs>']
for n in nodes:
    svg.append(f'<rect x="{n["x"]}" y="{n["y"]}" width="{n["w"]}" height="{n["h"]}" rx="24" fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"/>')
    svg.append(svg_text(n['x']+n['w']/2, n['y']+n['h']/2, n['text'], n['size'], True))
for e in edges:
    points = ' '.join(f'{x},{y}' for x, y in e['points'])
    svg.append(f'<polyline points="{points}" fill="none" stroke="#617184" stroke-width="2" marker-start="url(#a)" marker-end="url(#a)"/>')
for t in texts:
    svg.append(svg_text(t['x'], t['y'], t['text'], t['size'], t['bold']))
svg.append('</svg>')
svg_content = '\n'.join(svg)
stem = 'sb-ispgm-system-design'
(OUT / f'{stem}.svg').write_text(svg_content, encoding='utf-8')
(ROOT / 'tmp/architecture/system-design-render.json').write_text(
    json.dumps(dict(width=W, height=H, nodes=nodes, edges=edges, texts=texts)), encoding='utf-8')

caption = 'Figure 1. System Design of the Smart Barangay Information System for Population and Governance Management.'
paragraphs = [
    'Figure 1 illustrates the three-layer design of the Smart Barangay Information System for Population and Governance Management (SB-ISPGM). The presentation layer provides interfaces for residents, barangay secretaries, and municipal LGU staff. Residents access services through the web portal or Android application, while barangay and municipal personnel use web dashboards. Authentication and access controls restrict protected functions according to account approval, user role, assigned barangay, and record ownership.',
    'The application layer uses Laravel controllers and services to validate submissions and process authorized operations. Its modules support account approvals; resident, household, and Registry of Barangay Inhabitants (RBI) records; document requests and payment review; resident concerns and replies; migration monitoring and forecasting; geographic information system (GIS) maps and offline household synchronization; reports and activity history; and the English and Cebuano database assistant. These modules represent available system functions rather than a mandatory sequence of activities.',
    'The data layer consists of a MySQL database and protected file storage. MySQL maintains structured records, including user accounts, inhabitants, households, RBI submissions, migration events, document transactions, and concerns. Protected file storage holds supporting materials such as payment receipts, signatures, and attachments. The application retrieves or updates these resources after applying the relevant access and validation rules. Double-headed arrows represent requests and responses or the exchange of information with storage.',
    'Migration forecasts are computed in PHP from sufficient, staff-verified monthly arrival and departure history, with net migration calculated as predicted arrivals minus predicted departures. The browser-based offline household workflow queues records locally and synchronizes them when connectivity returns; the Android resident application uses the resident API and requires connectivity. GCash payments take place outside the system, and submitted payment evidence is reviewed by authorized barangay staff.'
]
(OUT / f'{stem}-explanation.txt').write_text(caption + '\n\n' + '\n\n'.join(paragraphs) + '\n', encoding='utf-8')
html = ('<!doctype html><html lang="en"><meta charset="utf-8">'
        '<meta name="viewport" content="width=device-width,initial-scale=1">'
        '<title>SB-ISPGM System Design</title>'
        '<style>body{max-width:960px;margin:40px auto;padding:0 24px;color:#182a3b;'
        'font:17px/1.65 Georgia,serif}h1{font:700 30px Arial,sans-serif;margin-bottom:24px}'
        'figure{margin:0}svg{width:100%;height:auto}figcaption{font-weight:bold;text-align:center;'
        'margin:18px 0 28px}p{text-align:justify}@media print{body{margin:0;max-width:none;'
        'font-size:11pt}figure{break-inside:avoid}h1{font-size:20pt}}</style>'
        '<h1>Design of the System</h1><figure>' + svg_content + '<figcaption>' + escape(caption) +
        '</figcaption></figure>' + ''.join('<p>'+escape(p)+'</p>' for p in paragraphs) + '</html>')
(OUT / f'{stem}.html').write_text(html, encoding='utf-8')
print(OUT / f'{stem}.html')
