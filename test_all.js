const ms = require('milsymbol');

// Updated NATO_ENTITIES matching intelligence.js after PLANOPS alignment
const MY_ENTITIES = {
    '01': ['110100','110200','110300','110400','110500','110600','110700',
           '120100','120200','120300','120400','120500','120600',
           '130100','130200','140000'],
    '02': ['110000'],
    '05': ['110100','110200'],
    '11': ['110000','110100','110200','110300'],
    '15': ['110100','110101','110102','110103','110200','110300','110400','110500','110600',
           '110700','110800','110900','111000','111100','111200','111300','111400',
           '120000','120100','120101','120200','120300','120400','120500','120600','120700',
           '130000','130100','130200','130300','130400',
           '140000','150000','150100','150200','160000','170000'],
    '20': ['110100','110200','110300','110400','110500','110600','110700','110701',
           '110800','110900','111000','111100'],
    '27': ['110100','110200','110300','110400'],
    '30': ['120000','120100','120200','120300','130000','130100'],
    '35': ['110100','110101','110102','110103','120000','130000'],
    '36': ['110100','110200','110300','110400','110500','120000'],
    '40': ['110100','110200']
};

function testSidc(ss, entity) {
    let sidc = '10' + '0' + '3' + ss + '0' + '0' + '00' + entity + '00' + '00';
    try {
        let svg = new ms.Symbol(sidc, { size: 22 }).asSVG();
        return svg.includes('?') ? 'HAS_QUESTION_MARK' : 'OK';
    } catch(e) { return 'ERROR'; }
}

console.log("====== Testing all entities for ? icon ======\n");
let bad = [];
let total = 0;

for (let ss in MY_ENTITIES) {
    MY_ENTITIES[ss].forEach(entity => {
        total++;
        let result = testSidc(ss, entity);
        if (result !== 'OK') {
            bad.push({ ss, entity, result });
        }
    });
}

console.log(`Tested ${total} entities across ${Object.keys(MY_ENTITIES).length} symbol sets`);
if (bad.length === 0) {
    console.log("\nALL CLEAR - No ? icons found!");
} else {
    console.log(`\nFOUND ${bad.length} bad entities:`);
    bad.forEach(b => console.log(`  SS=${b.ss} Entity=${b.entity} - ${b.result}`));
}
