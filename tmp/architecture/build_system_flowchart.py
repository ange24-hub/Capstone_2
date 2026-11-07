from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=3000,3710
nodes=[];edges=[];texts=[]
INK='#243447'
def node(cx,cy,w,h,label,shape='box',fill='#F2F6FB',stroke='#6482A5',size=30):
    nodes.append(dict(x=cx-w/2,y=cy-h/2,w=w,h=h,text=label,shape=shape,fill=fill,stroke=stroke,size=size))
def decision(cx,cy,label,w=430,h=180):
    node(cx,cy,w,h,label,'diamond','#FFF6D8','#B99535',28)
def edge(points,label='',lx=0,ly=0):
    edges.append(dict(points=points,label=label,lx=lx,ly=ly))
def text(x,y,label,size=27,bold=False):
    texts.append(dict(x=x,y=y,text=label,size=size,bold=bold))
def connector(x,y):
    node(x,y,65,65,'A','ellipse','#FFFFFF','#6482A5',27)

text(1500,65,'SYSTEM FLOWCHART',49,True)
text(1500,120,'SB-ISPGM / RBIM | Municipality of Tomas Oppus',29)
text(1500,165,'Access, approval, role-based services and task completion',25)

node(1500,255,300,80,'START','ellipse','#E7F5ED','#4D916D')
node(1500,390,560,100,'Open the system',fill='#F2F6FB')
decision(1500,565,'Existing\naccount?')
edge([(1500,295),(1500,340)])
edge([(1500,440),(1500,475)])

node(1500,765,650,115,'Enter email / user ID\nand password','io','#EDF5FF','#4882BC')
edge([(1500,655),(1500,707.5)],'Yes',1545,683)
node(510,765,670,180,'Complete validated registration\nResident or Barangay Secretary\nCreate pending account and sign in',fill='#F0F6FC',size=29)
edge([(1285,565),(510,565),(510,675)],'No',1000,535)

decision(1500,975,'Credentials\nvalid?')
edge([(1500,822.5),(1500,885)])
node(2450,975,620,115,'Display login error\nRetry login',fill='#FFF0EE',stroke='#B96D62')
edge([(1715,975),(2140,975)],'No',1910,945)
edge([(2760,975),(2870,975),(2870,765),(1825,765)])
node(1500,1130,560,95,'Create authenticated session')
edge([(1500,1065),(1500,1082.5)],'Yes',1550,1076)

decision(1500,1310,'Approved account\nor Municipal DILG?',w=590,h=195)
edge([(1500,1177.5),(1500,1212.5)])
edge([(510,855),(510,1310),(1205,1310)],'Automatically signed in',810,1280)
node(2440,1285,650,130,'Display pending / rejected status\nProtected services unavailable',fill='#FFF0EE',stroke='#B96D62',size=28)
edge([(1795,1310),(2040,1310),(2040,1285),(2115,1285)],'No',1950,1275)
node(2440,1460,430,85,'Log out / close session')
node(2825,1460,190,75,'END','ellipse','#F0F2F5','#748498')
edge([(2440,1350),(2440,1417.5)])
edge([(2655,1460),(2730,1460)])

connector(1500,1510)
edge([(1500,1407.5),(1500,1477.5)],'Yes',1550,1440)
decision(1500,1650,'User role?',w=430,h=160)
edge([(1500,1542.5),(1500,1570)])

lanes=[
 (500,'Resident Dashboard','#EDF5FF','#4882BC','Choose a resident service\nSubmit document requests / receipts\nSubmit concerns and replies\nTrack requests and concern updates'),
 (1500,'Barangay Secretary Dashboard','#EFF8EE','#639560','Choose a barangay task\nReview resident applications\nManage households, RBI and migration\nReview requests, payments and concerns\nView maps, reports and forecasts'),
 (2500,'Municipal DILG Dashboard','#F4EEFC','#9270B1','Choose a municipal task\nReview secretary applications\nManage barangay configuration\nReview RBI / municipal summaries\nView maps, reports and forecasts'),
]
for x,title,fill,stroke,body in lanes:
    node(x,1860,740,110,title,fill=fill,stroke=stroke,size=31)
    node(x,2110,790,260,body,fill=fill,stroke=stroke,size=29)
    edge([(x,1915),(x,1980)])
edge([(1285,1650),(500,1650),(500,1805)],'Resident',830,1620)
edge([(1500,1730),(1500,1805)],'Barangay Secretary',1660,1770)
edge([(1715,1650),(2500,1650),(2500,1805)],'Municipal DILG',2110,1620)

node(1500,2390,820,110,'Check permissions, record scope\nand submitted information')
for x in [500,2500]: edge([(x,2240),(x,2290),(1500,2290),(1500,2335)])
edge([(1500,2240),(1500,2335)])
decision(1500,2580,'Request allowed\nand input valid?',w=550,h=180)
edge([(1500,2445),(1500,2490)])
node(2500,2580,650,120,'Display access or validation error\nCorrect input / choose another task',fill='#FFF0EE',stroke='#B96D62',size=28)
edge([(1775,2580),(2175,2580)],'No',1950,2550)
connector(2500,2740)
edge([(2500,2640),(2500,2707.5)])
text(2500,2800,'Return to the appropriate dashboard',24)

node(1500,2800,850,140,'Execute the permitted operation\nRead / update records as required\nGenerate requested reports or eligible forecasts',size=28)
edge([(1500,2670),(1500,2730)],'Yes',1550,2700)
node(1500,3020,850,120,'Display result, status or report\nShow data limitations when applicable','io','#EDF5FF','#4882BC',29)
edge([(1500,2870),(1500,2960)])
decision(1500,3230,'Another task?',w=450,h=170)
edge([(1500,3080),(1500,3145)])
connector(850,3230)
edge([(1275,3230),(882.5,3230)],'Yes',1070,3195)
text(850,3290,'Return to the appropriate dashboard',24)
node(1500,3400,620,95,'Log out and invalidate session')
edge([(1500,3315),(1500,3352.5)],'No',1550,3335)
node(1500,3530,300,80,'END','ellipse','#E7F5ED','#4D916D')
edge([(1500,3447.5),(1500,3490)])

text(850,1020,'Registration applies to Residents and Secretaries.\nMunicipal DILG accounts are provisioned separately.',24)
text(500,1460,'Approval: Residents - Barangay Secretary\nSecretaries - Municipal DILG',25)
text(500,2475,'Select one task at a time.\nThe task lists are menu options,\nnot mandatory sequential steps.',25)
text(500,2850,'Forecasts require sufficient verified\nmonthly migration history.\nOtherwise, the system shows data limitations.',25)
text(1500,3620,'Oval: start/end | Rectangle: action | Diamond: decision | Parallelogram: input/output | A: return connector',25)
text(1500,3670,'Overview of normal system use. Registration validation and specialist service workflows are summarized.',23)

def st(x,y,value,size,bold=False):
    lines=value.split('\n')
    return ''.join(f'<text x="{x}" y="{y+(i-(len(lines)-1)/2)*size*1.22}" text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="{INK}">{escape(v)}</text>' for i,v in enumerate(lines))
svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="100%" height="100%" fill="white"/>',
'<defs><marker id="a" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto"><path d="M0,1 L9,5 L0,9" fill="none" stroke="#617184" stroke-width="1.6"/></marker></defs>']
for n in nodes:
    x,y,w,h=n['x'],n['y'],n['w'],n['h'];shape=n['shape']
    attrs=f'fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"'
    if shape=='ellipse':svg.append(f'<ellipse cx="{x+w/2}" cy="{y+h/2}" rx="{w/2}" ry="{h/2}" {attrs}/>')
    elif shape in ['diamond','io']:
        pts=[(x+w/2,y),(x+w,y+h/2),(x+w/2,y+h),(x,y+h/2)] if shape=='diamond' else [(x+35,y),(x+w,y),(x+w-35,y+h),(x,y+h)]
        svg.append(f'<polygon points="{" ".join(f"{a},{b}" for a,b in pts)}" {attrs}/>')
    else:svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" {attrs}/>')
    svg.append(st(x+w/2,y+h/2,n['text'],n['size'],True))
for e in edges:
    svg.append(f'<polyline points="{" ".join(f"{x},{y}" for x,y in e["points"])}" fill="none" stroke="#617184" stroke-width="2" marker-end="url(#a)"/>')
    if e['label']:svg.append(st(e['lx'],e['ly'],e['label'],22))
for t in texts:svg.append(st(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-system-flowchart.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/system-flowchart-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
