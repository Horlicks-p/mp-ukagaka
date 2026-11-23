"""Reproduce the first locally traced rig. Generated/private assets stay in Studio/projects."""
import hashlib
import json
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parent
PROJECT = ROOT / 'studio/projects/frieren-chest'
ORIGINAL = ROOT.parents[1] / 'plan/mavatar-plan/frieren-chest-fullbody.png'
DY = 15

def ellipse(x, y, rx, ry, **extra):
    return dict(cx=x, cy=y + DY, rx=rx, ry=ry, **extra)

def band(a, b):
    return [a + DY, b + DY]

def points(values):
    return [[x, y + DY] for x, y in values]

def strand(name, nodes, sigma, maximum=5):
    return dict(name=name, nodes=points(nodes), sigma=sigma, k=1, max=maximum)

if __name__ == '__main__':
    PROJECT.mkdir(parents=True, exist_ok=True)
    original = Image.open(ORIGINAL).convert('RGBA')
    assert original.size == (1034, 1521), 'Trace belongs to the supplied 1034 x 1521 PNG'
    master = Image.new('RGBA', (1034, 1551))
    master.paste(original, (0, DY))
    master.save(PROJECT / 'source.png')
    # A complete neutral poster; built/base.png deliberately has its eyes removed.
    master.resize((467, 700), Image.Resampling.LANCZOS).save(PROJECT / 'poster.png')
    rig = dict(
        version=1, image=dict(width=1034, height=1551),
        head=ellipse(550, 190, 260, 420, shiftX=25, shiftY=18,
                     pivotX=540, pivotY=325 + DY, maxRoll=0.10,
                     weightBand=band(305, 385), turnBand=band(310, 400)),
        body=dict(pivotX=540, pivotY=650 + DY, maxRoll=0.018,
                  breathBand=band(440, 675), rollBand=band(440, 675),
                  hip=dict(shift=4, band=band(440, 675)),
                  chest=ellipse(550, 450, 90, 75),
                  shoulders=[ellipse(340, 415, 60, 65), ellipse(747, 425, 60, 65)]),
        face=dict(nose=ellipse(550, 252, 22, 25), mouth=ellipse(542, 285, 35, 18),
                  eyeA=ellipse(498, 211, 46, 30), eyeB=ellipse(600, 224, 47, 30),
                  earL=ellipse(383, 192, 62, 38), earR=ellipse(709, 218, 48, 30),
                  brow=ellipse(559, 181, 95, 22, band=band(183, 200)),
                  jaw=ellipse(542, 307, 70, 27, band=band(290, 315))),
        eyes=[
            dict(opening=points([(476,203),(480,201),(485,200),(491,199),(497,200),
                 (503,201),(509,203),(515,207),(520,211),(520,216),(516,220),
                 (510,223),(503,224),(497,223),(491,222),(485,219),(481,215),(478,210)]),
                 roi=points([(460,190),(476,182),(499,182),(521,188),(534,203),
                             (533,218),(523,232),(501,239),(480,231),(469,216)])),
            dict(opening=points([(635,224),(630,219),(624,217),(618,215),(612,215),
                 (605,215),(598,216),(591,218),(585,222),(579,227),(578,232),
                 (582,236),(589,240),(596,242),(604,242),(612,240),(620,237),(627,232)]),
                 roi=points([(570,216),(586,203),(608,199),(629,204),(645,215),
                             (647,231),(632,245),(611,252),(589,249),(574,239)]))],
        mouth=dict(cx=542, cy=284 + DY, angle=0.055, halfLen=10, bow=-1,
                   area=ellipse(542, 284, 22, 15, angle=0.055)),
        cheeks=points([(481,250),(624,261)]),
        strands=[
            strand('bang-left', [(543,107),(523,140),(493,161),(470,174)], 15, 3),
            strand('bang-right', [(552,111),(578,151),(617,183),(657,199)], 17, 3),
            strand('face-left', [(453,82),(433,147),(439,215),(469,266)], 15, 3),
            strand('face-right', [(665,107),(678,174),(667,239),(638,275)], 14, 3),
            strand('pony-left', [(435,58),(392,139),(381,255),(400,320)], 20, 4),
            strand('pony-right', [(674,73),(711,159),(724,267),(718,334)], 18, 4),
            strand('long-left-outer', [(403,314),(427,394),(443,483),(450,568)], 15, 4),
            strand('long-left-inner', [(430,318),(457,403),(481,490),(479,572)], 14, 4),
            strand('long-right-inner', [(654,323),(628,407),(624,487),(637,566)], 14, 4),
            strand('long-right-outer', [(702,323),(686,405),(683,483),(666,563)], 16, 4)],
        accessories=[dict(name='earring-left',pivot=[433,252+DY],tip=[434,298+DY],
                          split=0.4,box=[421,252+DY,447,304+DY],color=dict(redness=0.27,minRed=60)),
                     dict(name='earring-right',pivot=[626,282+DY],tip=[625,324+DY],
                          split=0.4,box=[611,280+DY,641,331+DY],color=dict(redness=0.27,minRed=60))],
        mesh=dict(baseCell=14,fine=dict(x0=300,y0=0,x1=790,y1=620+DY,cell=7),
                  handCell=9,tasselCell=6,eyeBallCell=3,eyeCell=3,spriteCell=3),
        view=dict(padTop=0.015,padSide=0.025,gazeCenter=[550,218+DY],irisTravel=3))
    (PROJECT / 'rig.draft.json').write_text(json.dumps(rig, indent=2), encoding='utf-8')
    (PROJECT / 'avatar.json').write_text(json.dumps(dict(format='mesh-avatar',version=1,
        name='Frieren — Chest Study',description='Local MP Ukagaka mesh experiment'),indent=2),encoding='utf-8')
    (PROJECT / 'work').mkdir(exist_ok=True)
    (PROJECT / 'work/source-provenance.json').write_text(json.dumps(dict(
        original=str(ORIGINAL),sha256=hashlib.sha256(ORIGINAL.read_bytes()).hexdigest(),
        originalSize=[1034,1521],masterSize=[1034,1551],offset=[0,DY],
        note='Transparent geometry padding only; original pixels unchanged.'),indent=2),encoding='utf-8')
    print(f'Prepared {PROJECT}: master 1034x1551, original pixels offset by y={DY}.')
