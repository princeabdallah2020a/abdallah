/* Wotoyi prototype - DEMO DATA ONLY (illustrative, not real listings, prices or dealers).
   All records are shown with a "Demo" badge. Images are the placeholder SVG illustrations of wotoyi/images/cars. */
window.WY = window.WY || {};
(function (WY) {
  var IMG = '../images/cars/';
  var dealers = [
    { id: 'almadies', name: 'Almadies Prestige', city: 'dakar', verified: true,  hours: [9, 19], initials: 'AP' },
    { id: 'corniche', name: 'Corniche Auto Plus', city: 'dakar', verified: true,  hours: [8, 18], initials: 'CA' },
    { id: 'thies',    name: 'Thiès Mobilité',     city: 'thies', verified: true,  hours: [8, 18], initials: 'TM' },
    { id: 'petite',   name: 'Petite-Côte Occasions', city: 'mbour', verified: false, hours: [9, 18], initials: 'PO' }
  ];
  /* body: suv|berline|pickup|4x4|citadine|monospace|utilitaire. fuel: essence|diesel|hybride|phev|electrique|gpl */
  var V = [
    { id: 1, mk: 'Toyota', md: 'Land Cruiser Prado', ver: '2.8 D-4D VX', yr: 2021, body: '4x4', fuel: 'diesel', tr: 'auto', drive: '4x4', km: 68000, city: 'dakar', price: 38500000, cond: 'occasion', customs: 'cleared', origin: 'Émirats arabes unis', owners: 2, seats: 7, doors: 5, col: 'gris', img: 'hero-suv', insp: true, dealer: 'almadies', spons: true, neg: true, posted: 3,
      eq: ['ac', 'heated', 'sunroof', 'cruise', 'keyless', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'screen', 'carplay', 'bt', 'gps', 'alloy', 'led', 'leather', 'seats7'],
      s: { cc: 2755, cyl: 4, hp: 177, torque: 450, gears: 6, length: 4780, width: 1885, height: 1845, wheelbase: 2790, tank: 87 },
      hist: { service: true, accident: true, warranty: true }, com: { fin: true, trade: true, deliv: true },
      d: 'Quatre-roues-motrices de référence, entretenu en concession, deux propriétaires. Idéal pour la ville comme pour la piste.' },
    { id: 2, mk: 'Toyota', md: 'Hilux', ver: 'Double cabine 2.4 D-4D', yr: 2022, body: 'pickup', fuel: 'diesel', tr: 'manuelle', drive: '4x4', km: 41000, city: 'dakar', price: 24900000, cond: 'occasion', customs: 'cleared', origin: 'Japon', owners: 1, seats: 5, doors: 4, col: 'bleuatl', img: 'pickup-atlantic', insp: true, dealer: 'almadies', spons: false, neg: true, posted: 9,
      eq: ['ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'screen', 'bt', 'alloy', 'roofbars'], s: { cc: 2393, hp: 150, gears: 6, seats: 5 },
      hist: { service: true, accident: true }, com: { fin: true, deliv: true }, d: 'Pick-up double cabine, première main, benne protégée. Disponible pour essai à Dakar.' },
    { id: 3, mk: 'Hyundai', md: 'Tucson', ver: '1.6 CRDi Executive', yr: 2020, body: 'suv', fuel: 'diesel', tr: 'auto', drive: 'fwd', km: 74000, city: 'thies', price: 15800000, cond: 'venant', customs: 'transit', origin: 'France', owners: 1, seats: 5, doors: 5, col: 'blancnacre', img: 'suv-pearl', insp: false, dealer: 'thies', spons: false, neg: true, posted: 5,
      eq: ['ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'screen', 'carplay', 'bt', 'alloy', 'led'], s: { cc: 1598, hp: 136 }, hist: { service: true }, com: { trade: true }, d: 'Véhicule venant de France, en cours de dédouanement. Carnet d’entretien disponible.' },
    { id: 4, mk: 'Kia', md: 'Sportage', ver: '2.0 Essence', yr: 2019, body: 'suv', fuel: 'essence', tr: 'auto', drive: 'fwd', km: 88000, city: 'dakar', price: 13400000, cond: 'occasion', customs: 'cleared', origin: 'Corée du Sud', owners: 2, seats: 5, doors: 5, col: 'ambre', img: 'suv-amber', insp: false, dealer: 'corniche', spons: false, neg: true, posted: 12,
      eq: ['ac', 'airbags', 'abs', 'camera', 'screen', 'bt', 'alloy'], s: {}, hist: { service: false }, com: { trade: true, deliv: true }, d: 'SUV compact confortable, climatisation efficace, idéal pour un usage familial.' },
    { id: 5, mk: 'Toyota', md: 'RAV4', ver: 'Hybride 2.5 Dynamic', yr: 2022, body: 'suv', fuel: 'hybride', tr: 'auto', drive: 'awd', km: 39000, city: 'dakar', price: 29500000, rent: { day: 65000, month: 1500000 }, cond: 'occasion', customs: 'cleared', origin: 'Japon', owners: 1, seats: 5, doors: 5, col: 'vertsauge', img: 'suv-sage', insp: true, dealer: 'corniche', spons: true, neg: false, posted: 2,
      eq: ['ac', 'heated', 'cruise', 'keyless', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'lane', 'screen', 'carplay', 'bt', 'gps', 'alloy', 'led'],
      s: { cc: 2487, hp: 222, gears: 'e-CVT', length: 4600, width: 1855, height: 1685, wheelbase: 2690, seats: 5 }, cons: { euro: 'Euro 6' },
      hist: { service: true, accident: true, warranty: true }, com: { fin: true, trade: true, deliv: true }, d: 'Hybride essence-électrique, transmission intégrale, première main. Disponible à la vente et à la location.' },
    { id: 6, mk: 'Toyota', md: 'Corolla', ver: '1.8 Hybride', yr: 2021, body: 'berline', fuel: 'hybride', tr: 'auto', drive: 'fwd', km: 52000, city: 'dakar', price: 16900000, cond: 'occasion', customs: 'cleared', origin: 'Japon', owners: 1, seats: 5, doors: 4, col: 'noirobs', img: 'sedan-obsidian', insp: false, dealer: 'almadies', spons: false, neg: true, posted: 7,
      eq: ['ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'screen', 'carplay', 'bt', 'alloy', 'led', 'keyless'], s: { cc: 1798, hp: 122 }, cons: { euro: 'Euro 6' }, hist: { service: true, accident: true }, com: { fin: true, trade: true }, d: 'Berline hybride sobre en carburant, très demandée à Dakar. Entretien à jour.' },
    { id: 7, mk: 'Hyundai', md: 'Accent', ver: '1.4 Essence', yr: 2018, body: 'berline', fuel: 'essence', tr: 'manuelle', drive: 'fwd', km: 97000, city: 'mbour', price: 6200000, cond: 'occasion', customs: 'cleared', origin: 'Corée du Sud', owners: 3, seats: 5, doors: 4, col: 'rougerubis', img: 'sedan-ruby', insp: false, dealer: 'petite', spons: false, neg: true, posted: 21,
      eq: ['ac', 'airbags', 'abs', 'bt'], s: {}, hist: {}, com: { deliv: true }, d: 'Berline économique, simple et robuste. Visible à Mbour.' },
    { id: 8, mk: 'Peugeot', md: '508', ver: '2.0 BlueHDi GT Line', yr: 2019, body: 'berline', fuel: 'diesel', tr: 'auto', drive: 'fwd', km: 81000, city: 'saintlouis', price: 14900000, rent: { day: 45000, month: 1100000 }, cond: 'venant', customs: 'transit', origin: 'France', owners: 1, seats: 5, doors: 5, col: 'noirobs', img: 'sedan-obsidian', insp: false, dealer: 'thies', spons: false, neg: true, posted: 15,
      eq: ['ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'lane', 'screen', 'carplay', 'bt', 'gps', 'alloy', 'led', 'leather', 'keyless'], s: { cc: 1997, hp: 177, gears: 8 }, hist: { service: true }, com: { fin: true }, d: 'Berline de direction au design soigné, venant de France. Location possible avec ou sans chauffeur.' },
    { id: 9, mk: 'Nissan', md: 'X-Trail', ver: '2.5 Essence', yr: 2020, body: 'suv', fuel: 'essence', tr: 'auto', drive: 'awd', km: 63000, city: 'dakar', price: 14200000, rent: { day: 50000, month: 1200000 }, cond: 'occasion', customs: 'cleared', origin: 'Japon', owners: 2, seats: 7, doors: 5, col: 'ambre', img: 'suv-amber', insp: false, dealer: 'corniche', spons: false, neg: true, posted: 6,
      eq: ['ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'screen', 'bt', 'alloy', 'seats7'], s: { cc: 2488, hp: 171 }, hist: { service: true }, com: { trade: true, deliv: true }, d: 'SUV 7 places, transmission intégrale. Pratique pour les familles et les déplacements en groupe.' },
    { id: 10, mk: 'Ford', md: 'Ranger', ver: 'Wildtrak 3.2 TDCi', yr: 2020, body: 'pickup', fuel: 'diesel', tr: 'auto', drive: '4x4', km: 92000, city: 'thies', price: 22500000, cond: 'occasion', customs: 'cleared', origin: 'Afrique du Sud', owners: 2, seats: 5, doors: 4, col: 'bleuatl', img: 'pickup-atlantic', insp: false, dealer: 'thies', spons: false, neg: true, posted: 18,
      eq: ['ac', 'cruise', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'screen', 'carplay', 'bt', 'gps', 'alloy', 'leather', 'roofbars'], s: { cc: 3198, hp: 200 }, hist: { service: true }, com: { fin: true, deliv: true }, d: 'Pick-up haut de gamme Wildtrak, prêt pour le travail comme pour le week-end.' },
    { id: 11, mk: 'Mercedes-Benz', md: 'GLC', ver: '220 d 4MATIC', yr: 2019, body: 'suv', fuel: 'diesel', tr: 'auto', drive: 'awd', km: 77000, city: 'dakar', price: 31800000, cond: 'occasion', customs: 'cleared', origin: 'Allemagne', owners: 1, seats: 5, doors: 5, col: 'blancnacre', img: 'suv-pearl', insp: true, dealer: 'almadies', spons: true, neg: true, posted: 4,
      eq: ['ac', 'heated', 'sunroof', 'cruise', 'keyless', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'lane', 'screen', 'carplay', 'bt', 'gps', 'alloy', 'led', 'leather'], s: { cc: 1950, hp: 194, gears: 9 }, hist: { service: true, accident: true, warranty: true }, com: { fin: true, trade: true, deliv: true }, d: 'SUV premium, sellerie cuir, toit ouvrant. Inspection Wotoyi réalisée.' },
    { id: 12, mk: 'Renault', md: 'Duster', ver: '1.5 dCi Prestige', yr: 2024, body: 'suv', fuel: 'diesel', tr: 'manuelle', drive: 'fwd', km: 10, city: 'mbour', price: 12400000, cond: 'neuf', customs: 'cleared', origin: 'Maroc', owners: 0, seats: 5, doors: 5, col: 'vertsauge', img: 'suv-sage', insp: false, dealer: 'petite', spons: false, neg: false, posted: 1,
      eq: ['ac', 'airbags', 'abs', 'esp', 'camera', 'sensors', 'screen', 'bt', 'alloy'], s: { cc: 1461, hp: 115 }, hist: { warranty: true }, com: { fin: true, deliv: true }, d: 'Véhicule neuf, garantie constructeur selon conditions du concessionnaire.' }
  ];
  var EQ = { ac: 'comfort', heated: 'comfort', sunroof: 'comfort', cruise: 'comfort', keyless: 'comfort', airbags: 'safety', abs: 'safety', esp: 'safety', camera: 'safety', sensors: 'safety', lane: 'safety',
    screen: 'media', carplay: 'media', bt: 'media', gps: 'media', alloy: 'exterior', led: 'exterior', roofbars: 'exterior', leather: 'interior', seats7: 'interior' };
  var SERVICES = [
    { id: 's1', icon: 'wrench', city: 'dakar', dealer: 'corniche' },
    { id: 's2', icon: 'steering', city: 'dakar', dealer: 'almadies' },
    { id: 's3', icon: 'globe', city: 'dakar', dealer: 'corniche' },
    { id: 's4', icon: 'drop', city: 'thies', dealer: 'thies' }
  ];
  WY.D = {
    dealers: dealers, vehicles: V, eq: EQ, services: SERVICES, img: IMG,
    bodies: ['suv', 'berline', 'citadine', '4x4', 'pickup', 'monospace', 'utilitaire'],
    fuels: ['essence', 'diesel', 'hybride', 'phev', 'electrique', 'gpl'],
    cities: ['dakar', 'thies', 'saintlouis', 'mbour'],
    bodyIcon: { suv: 'suv', berline: 'sedan', citadine: 'hatch', '4x4': 'offroad', pickup: 'pickup', monospace: 'van', utilitaire: 'van' },
    colors: { gris: '#B9BDC6', bleuatl: '#2E5E8C', blancnacre: '#ECE9E2', ambre: '#D88A2B', vertsauge: '#7F9378', noirobs: '#14161C', rougerubis: '#A8242D' }
  };
})(window.WY);
