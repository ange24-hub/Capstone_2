from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=2800,3020
nodes=[];edges=[];texts=[]
def node(x,y,w,h,label='',shape='box',fill='#F0F8F8',stroke='#32888C',size=25):
    nodes.append(dict(x=x,y=y,w=w,h=h,text=label,shape=shape,fill=fill,stroke=stroke,size=size))
def text(x,y,label,size=24,bold=False):
    texts.append(dict(x=x,y=y,text=label,size=size,bold=bold))
def edge(points,label,lx,ly,both=False):
    edges.append(dict(points=points,label=label,lx=lx,ly=ly,both=both))

text(1400,60,'Data Flow Diagram of the Developed System',46,True)
text(1400,115,'SB-ISPGM / RBIM | Municipality of Tomas Oppus | Level 1',27)
node(1400,170,1355,2640,shape='boundary',stroke='#5283BD')
text(2045,210,'SB-ISPGM System Boundary',29,True)

actor_info={
 'resident': (80,660,'Resident\nWeb / Android user','#EDF5FF','#4882BC'),
 'secretary': (80,1410,'Barangay Secretary\nBarangay staff','#EFF8EE','#639560'),
 'dilg': (80,2180,'Municipal DILG User\nMunicipal oversight','#F4EEFC','#9270B1')
}
for x,y,label,fill,stroke in actor_info.values():
    node(x,y,330,150,label,fill=fill,stroke=stroke,size=26)

processes=[
 ('P1',280,'Manage Accounts and Approvals',['User registration and login','Role and account validation','Secretary approval decisions']),
 ('P2',790,'Manage Registry and Migration',['Household and resident records','RBI submissions and verification','Migration events and barangay setup']),
 ('P3',1300,'Process Requests and Payments',['Document request processing','Receipt and payment review','Request decisions and status updates']),
 ('P4',1810,'Manage Concerns and Replies',['Resident concerns and attachments','Staff responses and follow-up','Concern status and conversation history']),
 ('P5',2320,'Generate Reports and Forecasts',['RBI and municipal summaries','Geographic maps and service reports','Verified migration history analysis','Arrival, departure and net forecasts']),
]
for pid,y,title,items in processes:
    node(1450,y,470,340,shape='process',fill='#F1F6FD',stroke='#4879B1')
    text(1685,y+34,pid,29,True)
    # Header divider is encoded as a line without an arrow.
    edges.append(dict(points=[(1450,y+66),(1920,y+66)],label='',lx=0,ly=0,plain=True))
    text(1685,y+105,title,25,True)
    for j,item in enumerate(items): text(1685,y+165+j*43,item,23)

# Distinct bent paths preserve one external symbol per user group.
# IN/OUT are explicitly relative to the process connected to that path.
connections=[
 ('resident',690,450,390,'IN: Registration / login details\nOUT: Account status'),
 ('secretary',1435,530,475,'IN: Secretary registration / login\nOUT: Account and approval status'),
 ('dilg',2205,610,560,'IN: Login / secretary approval decisions\nOUT: Account status / secretary applications'),
 ('secretary',1460,690,900,'IN: Household / RBI data / migration events\nOUT: Barangay records / submission status'),
 ('dilg',2230,770,1020,'IN: Barangay configuration\nOUT: Configuration records'),
 ('resident',720,850,1420,'IN: Document requests / payment evidence\nOUT: Request status / payment instructions'),
 ('secretary',1490,930,1540,'IN: Request and payment review decisions\nOUT: Pending requests / receipts'),
 ('resident',755,1010,1930,'IN: Concerns / replies / attachments\nOUT: Concern updates / staff replies'),
 ('secretary',1520,1090,2050,'IN: Responses / concern status updates\nOUT: Resident concerns / conversations'),
 ('secretary',1545,1170,2440,'IN: Report / map / forecast criteria\nOUT: Barangay reports / maps / estimates'),
 ('dilg',2285,1250,2610,'IN: Report / map / forecast criteria\nOUT: RBI reports / summaries / maps / estimates'),
]
for index,(actor,source_y,lane,target_y,label) in enumerate(connections):
    lane=450+index*38
    # Keep labels beside processes, clear of the routing corridor.
    edge([(410,source_y),(lane,source_y),(lane,target_y),(1450,target_y)],label,1125,target_y-43,True)

stores=[('D1',380,'Accounts and approvals'),('D2',890,'Registry and migration records'),('D3',1400,'Requests, payments and files'),('D4',1910,'Concerns, replies and files')]
for sid,y,label in stores:
    if sid=='D2': label='Registry and\nmigration records'
    elif sid=='D3': label='Requests, payments\nand files'
    elif sid=='D4': label='Concerns, replies\nand files'
    else: label='Accounts and\napprovals'
    node(2350,y,340,125,sid+'\n'+label,shape='store')
    edge([(1920,y+62),(2350,y+62)],'Read / write',2135,y+36,True)

# Reporting reads shared stores. Repetition follows standard DFD layout practice.
for sid,y,label,flowlabel in [
 ('D2',2320,'Registry and\nmigration records','Read: registry, locations,\nverified monthly counts'),
 ('D3',2490,'Requests, payments\nand files','Read: service records'),
 ('D4',2660,'Concerns, replies\nand files','Read: concern records')]:
    node(2350,y,340,115,sid+'\n'+label,shape='store')
    target_y=y+57 if sid!='D4' else 2625
    pts=[(2350,y+57),(2200,y+57),(2200,target_y),(1920,target_y)] if sid=='D4' else [(2350,y+57),(1920,target_y)]
    edge(pts,flowlabel,2060 if sid=='D4' else 2125,y+18)

node(80,2510,650,295,shape='box',fill='#FFFFFF',stroke='#91A4B9')
text(405,2550,'Legend',28,True)
text(405,2600,'Rectangle: external entity',24)
text(405,2640,'Rounded rectangle: system process',24)
text(405,2680,'Parallel lines: logical data store',24)
text(405,2720,'Arrowhead: direction of data flow',24)
text(405,2760,'IN / OUT: relative to the process',24)
text(1400,2870,'Repeated D identifiers represent the same data stores. Crossings without a junction dot are separate flows.',23)
text(1400,2910,'Forecasts use verified migration history. Shared access checks, activity logging and email transport details are omitted.',23)
text(1400,2970,'Figure. Level 1 Data Flow Diagram of the Developed SB-ISPGM System',26,True)

def st(x,y,value,size,bold=False):
    lines=value.split('\n')
    return ''.join(f'<text x="{x}" y="{y+(i-(len(lines)-1)/2)*size*1.22}" text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="#243447">{escape(v)}</text>' for i,v in enumerate(lines))
svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="100%" height="100%" fill="white"/>',
 '<defs><marker id="a" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto-start-reverse"><path d="M0,1 L9,5 L0,9" fill="none" stroke="#617184" stroke-width="1.6"/></marker></defs>']
for n in nodes:
    x,y,w,h=n['x'],n['y'],n['w'],n['h']
    if n['shape']=='store':
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{n["fill"]}"/><path d="M{x},{y}h{w} M{x},{y+h}h{w}" stroke="{n["stroke"]}" stroke-width="3"/>')
    else:
        fill='none' if n['shape']=='boundary' else n['fill']
        dash='stroke-dasharray="10 7"' if n['shape']=='boundary' else ''
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{24 if n["shape"]=="process" else 0}" fill="{fill}" stroke="{n["stroke"]}" stroke-width="2.5" {dash}/>')
    svg.append(st(x+w/2,y+h/2,n['text'],n['size'],True))
for e in edges:
    points=' '.join(f'{x},{y}' for x,y in e['points'])
    markers='' if e.get('plain') else 'marker-end="url(#a)"'+(' marker-start="url(#a)"' if e.get('both') else '')
    svg.append(f'<polyline points="{points}" fill="none" stroke="#617184" stroke-width="2" {markers}/>')
    # White label backplates isolate text from crossing routes.
    if e['label']:
        lines=e['label'].split('\n'); width=max(len(l) for l in lines)*11.8+16; height=len(lines)*28+10
        svg.append(f'<rect x="{e["lx"]-width/2}" y="{e["ly"]-height/2}" width="{width}" height="{height}" fill="white"/>')
        svg.append(st(e['lx'],e['ly'],e['label'],22))
for t in texts: svg.append(st(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-dfd-system-boundary.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/dfd-boundary-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts,backplates=True)),encoding='utf-8')
