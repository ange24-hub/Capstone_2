"""Verify and package the requested figures and tables, without editing the app."""
from pathlib import Path
import csv
import json
import zipfile
from xml.etree import ElementTree as ET

out=Path(__file__).resolve().parent
name='SBISPGM_Figures_Tables_Diagrams'
docx=out/(name+'.docx')
ns={'w':'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
with zipfile.ZipFile(docx) as archive:
    assert archive.testzip() is None
    for entry in archive.namelist():
        if entry.endswith(('.xml','.rels')):
            ET.fromstring(archive.read(entry))
    document=ET.fromstring(archive.read('word/document.xml'))
    assert len(document.findall('.//w:tbl',ns))==10
    assert len(document.findall('.//w:drawing',ns))==6
    assert len([x for x in archive.namelist() if x.startswith('word/media/')])==6
    text=' '.join(x.text or '' for x in document.findall('.//w:t',ns))
    assert '[TO COMPLETE' not in text
for path in sorted((out/'tables').glob('*.csv')):
    with path.open(encoding='utf-8-sig',newline='') as stream:
        rows=list(csv.reader(stream))
        assert rows and all(len(row)==len(rows[0]) for row in rows),path
readme='''SMART BARANGAY INFORMATION SYSTEM FOR POPULATION AND GOVERNANCE MANAGEMENT
FIGURES, TABLES, AND DIAGRAMS

Open SBISPGM_Figures_Tables_Diagrams.docx for all figures, editable tables, captions, and short explanations.
The HTML file is a printable preview; keep its figures folder beside it.

Figures (PNG):
1. architecture.png — Logical architecture
2. context.png — Context diagram
3. data-flow.png — Selected resident-service data flow diagram
4. workflow.png — Document request and payment review flowchart
5. use-cases.png — Use cases by user role
6. entities.png — Selected registry and RBI entity relationships

Editable tables (CSV):
1. User roles and access
2. System modules and outputs
3. Technologies used
4. Principal data entities
5. Input, process, output, and controls
6. Document and payment states
7. Resident concern transitions
8. Population and migration measures
9. Security and reliability controls
10. Recorded technical verification

These figures describe implementation, not verified field deployment or user-evaluation results. The DFD and entity model are explicitly selected views, rather than complete full-system models. The October 10, 2026 test table is historical project evidence.

No application source, configuration, database, or operational records were changed. All new files are documentation artifacts.
'''
(out/'Visuals_Readme.txt').write_text(readme,encoding='utf-8')
paths=[docx,out/(name+'.html'),out/'Visuals_Readme.txt']+sorted((out/'figures').glob('*.png'))+sorted((out/'tables').glob('*.csv'))
dest=out/(name+'.zip')
with zipfile.ZipFile(dest,'w',zipfile.ZIP_DEFLATED) as package:
    for path in paths:
        package.write(path,path.relative_to(out))
with zipfile.ZipFile(dest) as package:
    assert package.testzip() is None
print(json.dumps({'docx':str(docx),'zip':str(dest),'figures':6,'tables':10,'verified':'ZIP integrity, XML structure, embedded figures, table count, CSV consistency'},indent=2))
