from pathlib import Path
from html import escape
import json

ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/architecture'
W,H=2800,2730
nodes=[]; edges=[]; texts=[]
palette={'E1':('#EDF5FF','#4882BC'),'E2':('#EFF8EE','#639560'),'E3':('#F4EEFC','#9270B1')}
names={'E1':'E1  Resident','E2':'E2  Barangay\nSecretary','E3':'E3  Municipal\nDILG User'}

def node(x,y,w,h,label,shape='box',fill='#EDF8F8',stroke='#32888C',size=25):
    nodes.append(dict(x=x,y=y,w=w,h=h,text=label,shape=shape,fill=fill,stroke=stroke,size=size))
def actor(x,y,e):
    node(x,y,280,100,names[e],fill=palette[e][0],stroke=palette[e][1])
def process(x,y,label):
    node(x-120,y-120,240,240,label,'ellipse','#FFF7DE','#BA9330',22)
def edge(points,label,lx,ly):
    edges.append(dict(points=points,label=label,lx=lx,ly=ly))
def text(x,y,label,size=25,bold=False):
    texts.append(dict(x=x,y=y,text=label,size=size,bold=bold))

text(W/2,55,'SB-ISPGM / RBIM - Level 1 Data Flow Diagram',45,True)
text(W/2,110,'Municipality of Tomas Oppus | Resident, Barangay Secretary and Municipal DILG',28)

def group(ox,oy,num,title,left,right,store,storelabel,write,read,top=False):
    # Paired, separately labeled flows follow elbow routes around each process.
    def e(points,label,lx,ly):
        edge([(ox+x,oy+y) for x,y in points],label,ox+lx,oy+ly)
    process(ox+675,oy+430,num+'\n'+title)
    actor(ox+20,oy+380,left[0]); actor(ox+1050,oy+380,right[0])
    e([(300,405),(420,405),(420,390),(562,390)],left[1],425,342)
    e([(562,470),(420,470),(420,455),(300,455)],left[2],410,530)
    e([(1050,405),(925,405),(925,390),(788,390)],right[1],925,342)
    e([(788,470),(925,470),(925,455),(1050,455)],right[2],930,530)
    node(ox+515,oy+690,320,100,store+'\n'+storelabel,'store')
    e([(635,543),(635,615),(605,615),(605,690)],write,445,630)
    e([(745,690),(745,615),(715,615),(715,543)],read,935,630)
    if top:
        actor(ox+535,oy+95,'E3')
        e([(605,195),(605,260),(635,260),(635,317)],'Login details;\napproval decisions',475,255)
        e([(715,317),(715,260),(745,260),(745,195)],'Account status;\nsecretary applications',885,255)
    else:
        text(ox+675,oy+160,'PROCESS '+num,23,True)

group(35,155,'1.0','Manage Accounts\nand Approvals',
      ('E1','Registration /\nlogin details','Account status'),
      ('E2','Secretary registration /\nlogin details','Account and\napproval status'),
      'D1','Accounts and approvals','Account / approval\nupdates','Account / application\nrecords',True)
group(1415,155,'2.0','Manage Residents,\nRBI and Migration\nRecords',
      ('E2','Household / RBI data;\nmigration events','Barangay records;\nsubmission status'),
      ('E3','Barangay\nconfiguration','Configuration\nrecords'),
      'D2','Registry and\nmigration records','Validated records /\nconfiguration','Registry / migration\nhistory')
group(35,1035,'3.0','Process Document\nRequests and\nPayment Review',
      ('E1','Document requests;\npayment evidence','Request status;\npayment instructions'),
      ('E2','Request / payment\nreview decisions','Pending requests;\nreceipts; request records'),
      'D3','Service requests,\npayments and files','Requests, evidence\nand decisions','Request / payment\nrecords; files')
group(1415,1035,'4.0','Manage Concerns\nand Replies',
      ('E1','Concerns, replies\nand attachments','Concern updates;\nstaff replies'),
      ('E2','Responses; concern\nstatus updates','Resident concerns;\nconversation records'),
      'D4','Concerns, replies\nand files','Concerns, replies\nand status','Concern history\nand attachments')

# The reporting process combines shared stores; repeated identifiers keep routes clear.
process(1400,2340,'5.0\nGenerate Reports,\nMaps and Migration\nForecasts')
actor(250,2290,'E2'); actor(2270,2290,'E3')
edge([(530,2320),(850,2320),(850,2300),(1287,2300)],'Report / map / forecast\nselection criteria',870,2250)
edge([(1287,2380),(850,2380),(850,2360),(530,2360)],'Barangay reports, maps\nand migration estimates',870,2430)
edge([(2270,2320),(1950,2320),(1950,2300),(1513,2300)],'Report / map / forecast\nselection criteria',1930,2250)
edge([(1513,2380),(1950,2380),(1950,2360),(2270,2360)],'RBI reports; municipal summaries;\nmaps; migration estimates',1930,2430)
for x,sid,label in [(700,'D2','Registry and\nmigration records'),(1240,'D3','Service requests,\npayments and files'),(1780,'D4','Concerns, replies\nand files')]:
    node(x,1950,320,100,sid+'\n'+label,'store')
edge([(860,2050),(860,2140),(1340,2140),(1340,2236)],'RBI / location data;\nverified monthly migration counts',1030,2092)
edge([(1400,2050),(1400,2220)],'Service transaction\nrecords',1540,2120)
edge([(1940,2050),(1940,2180),(1460,2180),(1460,2236)],'Concern records',1810,2147)

text(W/2,2570,'Rectangle = external entity   |   Circle = process   |   Parallel lines = data store   |   Arrow = direction of data flow',25)
text(W/2,2620,'Repeated E / D identifiers refer to the same entities and stores. Data stores group related database records and protected files.',23)
text(W/2,2660,'Core business flows only. Shared authentication, activity logging and email transport details are omitted for readability.',23)

def st(x,y,value,size,bold=False):
    lines=value.split('\n')
    return ''.join(f'<text x="{x}" y="{y+(i-(len(lines)-1)/2)*size*1.22}" text-anchor="middle" dominant-baseline="middle" font-family="Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="#243447">{escape(v)}</text>' for i,v in enumerate(lines))
svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="100%" height="100%" fill="white"/>',
     '<defs><marker id="a" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto"><path d="M0,1 L9,5 L0,9" fill="none" stroke="#617184" stroke-width="1.6"/></marker></defs>']
for n in nodes:
    x,y,w,h=n['x'],n['y'],n['w'],n['h']
    if n['shape']=='ellipse':
        svg.append(f'<ellipse cx="{x+w/2}" cy="{y+h/2}" rx="{w/2}" ry="{h/2}" fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"/>')
    elif n['shape']=='store':
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{n["fill"]}"/><path d="M{x},{y}h{w} M{x},{y+h}h{w}" stroke="{n["stroke"]}" stroke-width="3"/>')
    else:
        svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="{n["fill"]}" stroke="{n["stroke"]}" stroke-width="2.5"/>')
    svg.append(st(x+w/2,y+h/2,n['text'],n['size'],True))
for e in edges:
    points=' '.join(f'{x},{y}' for x,y in e['points'])
    svg.append(f'<polyline points="{points}" fill="none" stroke="#617184" stroke-width="2" marker-end="url(#a)"/>')
    svg.append(st(e['lx'],e['ly'],e['label'],22))
for t in texts: svg.append(st(t['x'],t['y'],t['text'],t['size'],t['bold']))
svg.append('</svg>')
(OUT/'sb-ispgm-level-1-dfd-v2.svg').write_text('\n'.join(svg),encoding='utf-8')
(ROOT/'tmp/architecture/dfd-network-render.json').write_text(json.dumps(dict(width=W,height=H,nodes=nodes,edges=edges,texts=texts)),encoding='utf-8')
