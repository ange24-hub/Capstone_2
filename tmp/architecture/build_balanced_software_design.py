from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=1600,1430
nodes=[];edges=[];texts=[]
def box(x,y,w,h,label='',fill='#EFF5FC',stroke='#668BB5',size=28):
    nodes.append(dict(x=x,y=y,w=w,h=h,text=label,shape='process',fill=fill,stroke=stroke,size=size))
def line(points):
    edges.append(dict(points=points,label='',lx=0,ly=0,both=True))
def text(x,y,label,size=25,bold=False):
    texts.append(dict(x=x,y=y,text=label,size=size,bold=bold))

text(800,65,'Design of the Software',43,True)
text(800,117,'SB-ISPGM System',27)
box(80,190,440,110,'Resident Portal\nWeb / Android')
box(580,190,440,110,'Barangay Secretary\nDashboard')
box(1080,190,440,110,'Municipal DILG\nDashboard')
box(350,405,900,105,'Authentication and Access Control\nLogin, account approval and user permissions','#FFF8E8','#BAA16A',28)
line([(300,300),(300,350),(500,350),(500,405)])
line([(800,300),(800,405)])
line([(1300,300),(1300,350),(1100,350),(1100,405)])

box(100,620,1400,425,fill='#F7FAF9',stroke='#6D9C8B')
text(800,670,'Laravel Application',32,True)
text(800,712,'Controllers and services process the selected task',24)
line([(800,510),(800,620)])
text(1000,565,'Requests and responses',22)
modules=[
 'Resident and\nRBI Records',
 'Document Requests\nand Payment Review',
 'Concerns\nand Replies',
 'Migration Records\nand Forecasting',
 'Maps and\nOffline Synchronization',
 'Reports and\nActivity History',
]
for i,label in enumerate(modules):
    x=150+(i%3)*450;y=760+(i//3)*145
    box(x,y,400,105,label,'#ECF6F1','#80A995',28)

box(250,1180,500,115,'MySQL Database\nAccounts and system records','#F2F5F8','#899BAC',28)
box(850,1180,500,115,'File Storage\nReceipts, signatures and attachments','#F2F5F8','#899BAC',27)
line([(500,1045),(500,1180)])
line([(1100,1045),(1100,1180)])
text(650,1110,'Read / write',22)
text(1250,1110,'Store / retrieve',22)
text(800,1360,'Figure. Software design of the developed SB-ISPGM system.',25)
text(800,1400,'Modules represent available functions, not a sequence of steps.',22)

def st(x,y,value,size,bold=False):
    lines=value.split('\n')
    return ''.join(f'<text x="{x}" y="{y+(i-(len(lines)-1)/2)*size*1.22}" text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="#243447">{escape(v)}</text>' for i,v in enumerate(lines))
svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="100%" height="100%" fill="white"/>',
'<defs><marker id="a" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto-start-reverse"><path d="M0,1 L9,5 L0,9" fill="none" stroke="#617184" stroke-width="1.6"/></marker></defs>']
for n in nodes:
    x,y,w,h=n['x'],n['y'],n['w'],n['h']
    svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="24" fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"/>')
    svg.append(st(x+w/2,y+h/2,n['text'],n['size'],True))
for e in edges:
    svg.append(f'<polyline points="{" ".join(f"{x},{y}" for x,y in e["points"])}" fill="none" stroke="#617184" stroke-width="2" marker-start="url(#a)" marker-end="url(#a)"/>')
for t in texts:svg.append(st(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-software-design-balanced.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/software-design-balanced-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
