from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=1400,1020
nodes=[];edges=[];texts=[]
def box(x,y,w,h,label,fill,stroke,size=27):
    nodes.append(dict(x=x,y=y,w=w,h=h,text=label,shape='process',fill=fill,stroke=stroke,size=size))
def line(points):
    edges.append(dict(points=points,label='',lx=0,ly=0,both=True))
def text(x,y,label,size,bold=False):
    texts.append(dict(x=x,y=y,text=label,size=size,bold=bold))

text(700,65,'Design of the Software',40,True)
text(700,115,'SB-ISPGM System',25)
box(80,190,360,110,'Resident Portal\nWeb / Android','#EFF5FC','#668BB5')
box(520,190,360,110,'Barangay Secretary\nDashboard','#EFF5FC','#668BB5')
box(960,190,360,110,'Municipal DILG\nDashboard','#EFF5FC','#668BB5')
box(360,450,680,210,'Laravel Application\n\nAccess control, records and services\nReports, maps and forecasting','#EFF8F5','#649B88',28)
line([(260,300),(260,370),(500,370),(500,450)])
line([(700,300),(700,450)])
line([(1140,300),(1140,370),(900,370),(900,450)])
box(240,810,400,110,'MySQL Database\nSystem records','#F5F6F8','#8B99AA')
box(760,810,400,110,'File Storage\nReceipts and attachments','#F5F6F8','#8B99AA')
line([(540,660),(540,730),(440,730),(440,810)])
line([(860,660),(860,730),(960,730),(960,810)])
text(700,980,'Figure. Simplified software design of the developed system.',23)

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
(OUT/'sb-ispgm-software-design-simple.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/software-design-simple-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
