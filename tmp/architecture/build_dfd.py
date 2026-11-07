from pathlib import Path
import json
from html import escape

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output' / 'architecture'
OUT.mkdir(parents=True, exist_ok=True)
W, H = 2300, 2370
nodes, edges, texts = [], [], []
colors = {'E1': ('#EDF5FF', '#4882BC'), 'E2': ('#EFF8EE', '#639560'), 'E3': ('#F4EEFC', '#9270B1')}
names = {'E1': 'E1  Resident', 'E2': 'E2  Barangay Secretary', 'E3': 'E3  Municipal DILG User'}

def text(x, y, value, size=23, bold=False):
    texts.append(dict(x=x, y=y, text=value, size=size, bold=bold))

def node(x, y, w, h, value, shape='box', fill='#FFFFFF', stroke='#64748B'):
    nodes.append(dict(x=x, y=y, w=w, h=h, text=value, shape=shape, fill=fill, stroke=stroke))

def flow(x1, y, x2, label):
    edges.append(dict(x1=x1, y=y, x2=x2, label=label))

text(W/2, 55, 'SB-ISPGM / RBIM - Level 1 Data Flow Diagram', 43, True)
text(W/2, 102, 'Municipality of Tomas Oppus | Core business processes | Municipal DILG', 26)
text(210, 157, 'EXTERNAL ENTITIES', 23, True)
text(1240, 157, 'SYSTEM PROCESSES', 23, True)
text(2075, 157, 'LOGICAL DATA STORES', 23, True)

rows = [
    ('1.0', 'Manage Accounts\nand Approvals', [
        ('E1', 'Registration / login details', 'Account status'),
        ('E2', 'Secretary registration / login details', 'Account and approval status'),
        ('E3', 'Login details; secretary approval decisions', 'Account status; secretary applications'),
    ], ('D1', 'Accounts and approvals'), 'Account / approval updates', 'Account and application records'),
    ('2.0', 'Manage Residents,\nRBI and Migration\nRecords', [
        ('E2', 'Household / resident data; RBI; migration events', 'Barangay records; submission status'),
        ('E3', 'Barangay configuration', 'Barangay configuration records'),
    ], ('D2', 'Registry and\nmigration records'), 'Validated records / configuration', 'Registry and migration history'),
    ('3.0', 'Process Document\nRequests and\nPayment Review', [
        ('E1', 'Document requests; payment evidence', 'Request status; payment instructions'),
        ('E2', 'Request and payment review decisions', 'Pending requests; receipts; request records'),
    ], ('D3', 'Service requests,\npayments and files'), 'Requests, evidence and decisions', 'Request / payment records; files'),
    ('4.0', 'Manage Concerns\nand Replies', [
        ('E1', 'Concerns; replies; attachments', 'Concern updates; staff replies'),
        ('E2', 'Responses; concern status updates', 'Resident concerns; conversation records'),
    ], ('D4', 'Concerns, replies\nand files'), 'Concerns, replies and status', 'Concern history and attachments'),
    ('5.0', 'Generate Reports,\nMaps and Migration\nForecasts', [
        ('E2', 'Report / map / forecast selection criteria', 'Barangay reports; maps; migration estimates'),
        ('E3', 'Report / map / forecast selection criteria', 'RBI reports; summaries; maps; forecasts'),
    ], None, '', ''),
]

for i, (number, title, actors, store, write_label, read_label) in enumerate(rows):
    y = 190 + i * 410
    node(1030, y+5, 420, 340, number+'\n'+title, 'process', '#FFF7DE', '#BA9330')
    for j, (actor, incoming, outgoing) in enumerate(actors):
        ay = y + (18 + j * 110 if len(actors) == 3 else 58 + j * 160)
        fill, stroke = colors[actor]
        node(35, ay, 360, 78, names[actor], 'box', fill, stroke)
        flow(395, ay+25, 1030, incoming)
        flow(1030, ay+66, 395, outgoing)
    if store:
        number_store, label = store
        node(1900, y+100, 360, 140, number_store+'\n'+label, 'store', '#EDF8F8', '#32888C')
        flow(1450, y+142, 1900, write_label)
        flow(1900, y+208, 1450, read_label)
    else:
        for j, (sid, label, data) in enumerate([
            ('D2', 'Registry and\nmigration records', 'RBI / locations; verified monthly counts'),
            ('D3', 'Service requests,\npayments and files', 'Service transaction records'),
            ('D4', 'Concerns, replies\nand files', 'Concern records'),
        ]):
            sy = y + 10 + j*110
            node(1900, sy, 360, 96, sid+'\n'+label, 'store', '#EDF8F8', '#32888C')
            flow(1900, sy+50, 1450, data)

text(W/2, 2260, 'Notation: rectangle = external entity; rounded rectangle = process; parallel lines = data store; arrow = data flow.', 23)
text(W/2, 2300, 'Repeated E and D identifiers refer to the same entities and stores. Stores include related database records and protected files.', 22)
text(W/2, 2335, 'Core business flows are shown. Authentication, activity logging and email transport details are omitted for readability.', 22)

svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">',
       '<rect width="100%" height="100%" fill="white"/>',
       '<defs><marker id="arrow" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto"><path d="M0,1 L9,5 L0,9" fill="none" stroke="#617184" stroke-width="1.6"/></marker></defs>']
def svgtext(x,y,value,size=23,bold=False):
    parts = value.split('\n')
    return ''.join(f'<text x="{x}" y="{y+(k-(len(parts)-1)/2)*size*1.28}" text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="#243447">{escape(part)}</text>' for k,part in enumerate(parts))

for n in nodes:
    x,y,w,h=n['x'],n['y'],n['w'],n['h']
    if n['shape']=='store':
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{n["fill"]}"/>')
        svg.append(f'<path d="M{x},{y}h{w} M{x},{y+h}h{w}" stroke="{n["stroke"]}" stroke-width="3"/>')
    else:
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{24 if n["shape"]=="process" else 0}" fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"/>')
    svg.append(svgtext(x+w/2,y+h/2,n['text'],28 if n['shape']=='process' else 25,True))
for e in edges:
    svg.append(f'<path d="M{e["x1"]},{e["y"]}H{e["x2"]}" fill="none" stroke="#617184" stroke-width="2" marker-end="url(#arrow)"/>')
    svg.append(svgtext((e['x1']+e['x2'])/2,e['y']-15,e['label'],22))
for t in texts:
    svg.append(svgtext(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-level-1-dfd.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/dfd-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
print(OUT/'sb-ispgm-level-1-dfd.svg')
