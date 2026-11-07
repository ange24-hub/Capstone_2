from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=2200,1980
nodes=[];edges=[];texts=[]
def node(x,y,w,h,label='',fill='#F1F6FD',stroke='#547EAE',size=28,shape='process'):
    nodes.append(dict(x=x,y=y,w=w,h=h,text=label,shape=shape,fill=fill,stroke=stroke,size=size))
def text(x,y,label,size=26,bold=False):
    texts.append(dict(x=x,y=y,text=label,size=size,bold=bold))
def edge(points,label='',lx=0,ly=0,both=True):
    edges.append(dict(points=points,label=label,lx=lx,ly=ly,both=both))

text(1100,65,'DESIGN OF THE SOFTWARE',47,True)
text(1100,118,'SB-ISPGM / RBIM | Modular Laravel application using MVC',28)
text(1100,205,'USER INTERFACES',24,True)
node(100,250,600,155,'Resident Portal\nWeb browser / Android application','#EDF5FF','#4882BC',28)
node(800,250,600,155,'Barangay Secretary Dashboard\nWeb browser','#EFF8EE','#639560',28)
node(1500,250,600,155,'Municipal DILG Dashboard\nWeb browser','#F4EEFC','#9270B1',28)
edge([(400,405),(400,520),(600,520),(600,575)])
edge([(1800,405),(1800,520),(1600,520),(1600,575)])
edge([(1100,405),(1100,575)],'HTTPS requests / responses',1360,470)

node(300,575,1600,125,'Routes and Access Control\nSessions / Sanctum tokens | Account approval | Role and record permissions','#F1F6FD','#547EAE',29)
edge([(1100,700),(1100,800)],'Authorized requests / results',1390,750)

node(100,800,2000,545,fill='#FAFCFE',stroke='#8DA5BF',shape='box')
text(1100,855,'CONTROLLERS AND APPLICATION SERVICES',29,True)
text(1100,902,'Validate input, perform the selected operation and prepare views or API responses',25)
modules=[
 ('Accounts and Approvals','Registration, account status\nand approval decisions'),
 ('Resident and RBI Records','Household records, registry\nand RBI submissions'),
 ('Requests and Payment Review','Document transactions\nand receipt verification'),
 ('Concerns and Replies','Resident messages, responses\nand concern status'),
 ('Migration and Forecasting','Verified history and\nnative PHP time-series models'),
 ('GIS and Offline Sync','Maps and browser household\ncapture / synchronization'),
 ('Reports and Exports','Population, RBI and\nmunicipal summaries'),
 ('Activity History','Record relevant actions\nand transaction activity'),
]
for i,(title,body) in enumerate(modules):
    col=i%4;row=i//4;x=135+col*490;y=960+row*175
    node(x,y,460,140,title+'\n\n'+body,'#F0F7F6','#7CABA4',23)

edge([(650,1345),(650,1440)],'Structured record access',865,1394)
edge([(1650,1345),(1650,1440)],'Controlled file access',1870,1394)
node(250,1440,800,125,'Eloquent Models\nRelationships, queries and persistence','#EEF7F7','#32888C',29)
node(1250,1440,800,125,'File Access and Storage Services\nAuthorized uploads and downloads','#FFF8E9','#BE943F',29)
edge([(650,1565),(650,1660)],'Read / write',780,1610)
edge([(1650,1565),(1650,1660)],'Store / retrieve',1810,1610)
node(250,1660,800,145,'MySQL Database\nAccounts, households, residents, RBI, migration,\nrequests, payments, concerns and activity records','#EEF7F7','#32888C',26,shape='store')
node(1250,1660,800,145,'Persistent File Storage\nReceipts, signatures and attachments\nPrivate files served through authorized routes','#FFF8E9','#BE943F',26,shape='store')

text(1100,1870,'MVC: Models manage records; Views present information; Controllers coordinate requests and services.',25)
text(1100,1910,'Modules are selectable functions, not sequential steps. Forecasts require sufficient verified monthly history.',24)
text(1100,1950,'Figure. Software design of the developed SB-ISPGM system.',27,True)

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
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{24 if n["shape"]=="process" else 0}" fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"/>')
    svg.append(st(x+w/2,y+h/2,n['text'],n['size'],True))
for e in edges:
    markers='marker-end="url(#a)"'+(' marker-start="url(#a)"' if e.get('both') else '')
    svg.append(f'<polyline points="{" ".join(f"{x},{y}" for x,y in e["points"])}" fill="none" stroke="#617184" stroke-width="2" {markers}/>')
    svg.append(st(e['lx'],e['ly'],e['label'],22))
for t in texts:svg.append(st(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-software-design.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/software-design-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
