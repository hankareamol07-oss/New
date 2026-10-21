import asyncio,os
from gen2 import render,MR,STD
jobs=[]
def J(std,sub,med,total,name):
    d=f'out2/TechGuruji_प्रथम_सत्र_2026-27/इयत्ता_{STD[std]}'; os.makedirs(d,exist_ok=True)
    jobs.append((std,sub,med,total,f'{d}/{STD[std]}_{name}_प्रथम_सत्र_2026-27.pdf'))
J(1,'Marathi','Marathi',20,'मराठी'); J(1,'Maths','Marathi',20,'गणित'); J(1,'Maths','English',20,'Maths'); J(1,'English','English',20,'English')
J(3,'EVS','Marathi',30,'परिसर_अभ्यास'); J(3,'Marathi','Marathi',30,'मराठी'); J(3,'Maths','Marathi',30,'गणित'); J(3,'English','English',30,'English')
J(4,'EVS Part 1','Marathi',30,'परिसर_अभ्यास_भाग१'); J(4,'EVS Part 2','Marathi',30,'परिसर_अभ्यास_भाग२'); J(4,'Marathi','Marathi',30,'मराठी'); J(4,'Maths','Marathi',30,'गणित'); J(4,'English','English',30,'English')
for s in (6,7):
    t=40 if s==6 else 50
    J(s,'Hindi','Hindi',t,'हिंदी'); J(s,'Science','Marathi',t,'विज्ञान'); J(s,'History & Civics','Marathi',t,'इतिहास_नागरिकशास्त्र'); J(s,'Geography','Marathi',t,'भूगोल'); J(s,'Marathi','Marathi',t,'मराठी'); J(s,'Maths','Marathi',t,'गणित'); J(s,'English','English',t,'English')
asyncio.run(render(jobs,'out2/TechGuruji_प्रथम_सत्र_2026-27/papers_2026-27_import.sql'))
