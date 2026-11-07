from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=2600,2790
nodes=[];edges=[];texts=[]
def node(x,y,w,h,value='',shape='box',fill='#FFFFFF',stroke='#7890AA',size=28):
    nodes.append(dict(x=x,y=y,w=w,h=h,text=value,shape=shape,fill=fill,stroke=stroke,size=size))
def text(x,y,value,size=26,bold=False):
    texts.append(dict(x=x,y=y,text=value,size=size,bold=bold))
def line(points):
    edges.append(dict(points=points,label='',lx=0,ly=0,plain=True))
def actor(x,y,name,color):
    node(x-27,y-105,54,54,shape='ellipse',stroke=color)
    line([(x,y-51),(x,y+40)])
    line([(x-60,y-12),(x+60,y-12)])
    line([(x,y+40),(x-55,y+115)])
    line([(x,y+40),(x+55,y+115)])
    text(x,y+163,name,29,True)
def usecase(x,y,value,fill,stroke):
    node(x-280,y-77,560,154,value,'ellipse',fill,stroke,29)

text(1300,65,'USE CASE DIAGRAM',47,True)
text(1300,118,'SB-ISPGM / RBIM | Municipality of Tomas Oppus',29)
text(1300,163,'Core business functions and authorized user roles',25)
node(550,215,1550,2365,stroke='#54799F')
text(1325,260,'SB-ISPGM System',31,True)
text(900,330,'RESIDENT SERVICES',23,True)
text(1750,330,'MUNICIPAL FUNCTIONS',23,True)
text(900,1310,'BARANGAY OPERATIONS',23,True)
text(1750,1150,'SHARED STAFF FUNCTIONS',23,True)

actor(200,740,'Resident','#4882BC')
actor(200,1900,'Barangay\nSecretary','#639560')
actor(2400,1020,'Municipal\nDILG User','#9270B1')

resident=[(460,'Request barangay\ndocuments'),(660,'Submit payment\nevidence'),(860,'Track document\nrequest status'),(1060,'Submit concerns\nand replies')]
for y,label in resident:
    usecase(900,y,label,'#EDF5FF','#4882BC')
    line([(260,728),(620,y)])

secretary=[(1420,'Review resident\napplications'),(1620,'Manage household\nand resident records'),(1820,'Prepare and submit\nRBI records'),(2020,'Record migration events\nand verify monthly history'),(2220,'Process document requests\nand review payments'),(2420,'Respond to concerns\nand update status')]
for y,label in secretary:
    usecase(900,y,label,'#EFF8EE','#639560')
    line([(260,1888),(620,y)])

dilg=[(460,'Review secretary\napplications'),(660,'Manage barangay\nconfiguration'),(860,'Review submitted RBI\nand municipal summaries')]
for y,label in dilg:
    usecase(1750,y,label,'#F4EEFC','#9270B1')
    line([(2340,1008),(2030,y)])

shared=[(1300,'View and export\nauthorized reports'),(1500,'View geographic\nmaps'),(1700,'View migration forecasts\nand evaluation results')]
for i,(y,label) in enumerate(shared):
    usecase(1750,y,label,'#FFF7DE','#BA9330')
    line([(2340,1008),(2030,y)])
    # Keep associations to common functions clear of the left-hand ovals.
    route_y=1170+i*38
    lane_x=1240+i*60
    line([(260,1888),(450-i*35,1888),(450-i*35,route_y),(lane_x,route_y),(lane_x,y),(1470,y)])

text(1750,2025,'ACCESS CONDITIONS',24,True)
text(1750,2110,'Protected functions require authentication\nand the appropriate role and record scope.',26)
text(1750,2220,'Resident and Secretary accounts\nrequire approval before accessing services.',26)
text(1750,2330,'Forecasts require sufficient verified\nmonthly migration history.',26)
text(1300,2650,'Stick figure = actor   |   Oval = use case   |   Solid line = association   |   Rectangle = system boundary',25)
text(1300,2700,'Associations show permitted participation, not execution order or data movement.',24)
text(1300,2745,'Common registration, login, logout and password-recovery interactions are omitted to focus on business functions.',23)

def st(x,y,value,size,bold=False):
    parts=value.split('\n')
    return ''.join(f'<text x="{x}" y="{y+(i-(len(parts)-1)/2)*size*1.22}" text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="#243447">{escape(p)}</text>' for i,p in enumerate(parts))
svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="100%" height="100%" fill="white"/>']
for n in nodes:
    x,y,w,h=n['x'],n['y'],n['w'],n['h']
    attrs=f'fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"'
    if n['shape']=='ellipse':svg.append(f'<ellipse cx="{x+w/2}" cy="{y+h/2}" rx="{w/2}" ry="{h/2}" {attrs}/>')
    else:svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" {attrs}/>')
    svg.append(st(x+w/2,y+h/2,n['text'],n['size'],True))
for e in edges:
    svg.append(f'<polyline points="{" ".join(f"{x},{y}" for x,y in e["points"])}" fill="none" stroke="#617184" stroke-width="2"/>')
for t in texts:svg.append(st(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-use-case-diagram.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/use-case-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
