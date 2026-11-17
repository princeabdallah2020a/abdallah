# -*- coding: utf-8 -*-
"""UI strings: key -> (FR source, EN demo translation, AR demo translation).
EN and AR are DEMO translations written for the prototype; they are NOT natively reviewed (founder blocker, see README).
Wolof is intentionally absent (needs native review; the WO button is disabled)."""
S = {}
def A(k, fr, en, ar): S[k] = (fr, en, ar)

# ---- page meta
A('title.home', 'Wotoyi · Marketplace automobile au Sénégal (prototype)', 'Wotoyi · Car marketplace in Senegal (prototype)', 'ووتويي · سوق السيارات في السنغال (نموذج أولي)')
A('title.search', 'Rechercher un véhicule · Wotoyi (prototype)', 'Search vehicles · Wotoyi (prototype)', 'البحث عن مركبة · ووتويي (نموذج أولي)')
A('title.vehicle', 'Fiche véhicule · Wotoyi (prototype)', 'Vehicle page · Wotoyi (prototype)', 'صفحة المركبة · ووتويي (نموذج أولي)')
A('desc.home', 'Prototype cliquable de la page d’accueil Wotoyi : recherche, véhicules, concessionnaires et inspection. Données de démonstration.', 'Clickable prototype of the Wotoyi home page. Demo data.', 'نموذج تفاعلي للصفحة الرئيسية. بيانات تجريبية.')
A('desc.search', 'Prototype de la recherche Wotoyi avec filtres complets. Données de démonstration.', 'Wotoyi search prototype with the full filter set. Demo data.', 'نموذج البحث مع مرشحات كاملة. بيانات تجريبية.')
A('desc.vehicle', 'Prototype de la fiche véhicule Wotoyi. Données de démonstration.', 'Wotoyi vehicle page prototype. Demo data.', 'نموذج صفحة المركبة. بيانات تجريبية.')

# ---- chrome
A('a11y.skip', 'Aller au contenu', 'Skip to content', 'انتقل إلى المحتوى')
A('a11y.close', 'Fermer', 'Close', 'إغلاق')
A('demo.badge', 'Démo', 'Demo', 'تجريبي')
A('demo.strip', 'Prototype de conception : toutes les annonces, prix, concessionnaires et chiffres sont des données de démonstration, non contractuelles.', 'Design prototype: all listings, prices, dealers and figures are demo data and not binding.', 'نموذج تصميمي: جميع الإعلانات والأسعار والوكلاء والأرقام بيانات تجريبية وغير ملزمة.')
A('demo.outofscope', 'Hors périmètre du prototype : cet écran sera livré avec la plateforme.', 'Outside the prototype scope: this screen ships with the platform.', 'خارج نطاق النموذج: ستتوفر هذه الشاشة مع المنصة.')
A('nav.home', 'Wotoyi, accueil', 'Wotoyi, home', 'ووتويي، الرئيسية')
A('nav.main', 'Navigation principale', 'Main navigation', 'التنقل الرئيسي')
A('nav.buy', 'Acheter', 'Buy', 'شراء')
A('nav.rent', 'Louer', 'Rent', 'إيجار')
A('nav.services', 'Services', 'Services', 'خدمات')
A('nav.dealers', 'Concessionnaires', 'Dealers', 'الوكلاء')
A('nav.inspection', 'Inspection', 'Inspection', 'الفحص')
A('nav.search', 'Rechercher', 'Search', 'بحث')
A('nav.favs', 'Favoris', 'Favourites', 'المفضلة')
A('nav.account', 'Mon compte', 'My account', 'حسابي')
A('nav.pro', 'Espace pro', 'Pro area', 'منطقة المحترفين')
A('nav.menu', 'Menu', 'Menu', 'القائمة')
A('lang.label', 'Langue', 'Language', 'اللغة')
A('lang.wolof', 'Wolof : en attente de relecture par un locuteur natif', 'Wolof: awaiting native-speaker review', 'الولوف: بانتظار مراجعة متحدث أصلي')
A('theme.label', 'Thème', 'Theme', 'المظهر')
A('theme.toggle', 'Basculer entre thème clair et sombre', 'Switch between light and dark theme', 'التبديل بين المظهر الفاتح والداكن')
A('theme.auto', 'Auto', 'Auto', 'تلقائي')
A('theme.light', 'Clair', 'Light', 'فاتح')
A('theme.dark', 'Sombre', 'Dark', 'داكن')
A('bn.home', 'Accueil', 'Home', 'الرئيسية')
A('bn.search', 'Rechercher', 'Search', 'بحث')
A('bn.favs', 'Favoris', 'Favourites', 'المفضلة')
A('bn.account', 'Compte', 'Account', 'الحساب')
A('bc.label', 'Fil d’Ariane', 'Breadcrumb', 'مسار التنقل')
A('bc.home', 'Accueil', 'Home', 'الرئيسية')
A('cursor.view', 'Voir', 'View', 'عرض')
A('common.seeall', 'Tout voir', 'See all', 'عرض الكل')
A('common.prev', 'Précédent', 'Previous', 'السابق')
A('common.next', 'Suivant', 'Next', 'التالي')
A('yes', 'Oui', 'Yes', 'نعم')
A('no', 'Non', 'No', 'لا')

# ---- footer + prototype controls
A('footer.mission', 'Wotoyi réunit véhicules, concessionnaires vérifiés, inspection indépendante et contact direct par WhatsApp pour acheter et louer en confiance.', 'Wotoyi brings together vehicles, verified dealers, independent inspection and direct WhatsApp contact so you can buy and rent with confidence.', 'تجمع ووتويي المركبات والوكلاء الموثقين والفحص المستقل والتواصل المباشر عبر واتساب لتشتري وتستأجر بثقة.')
A('footer.nav', 'Pied de page', 'Footer', 'التذييل')
A('footer.buy', 'Acheter', 'Buy', 'شراء')
A('footer.incity', 'Véhicules à', 'Vehicles in', 'مركبات في')
A('footer.rent', 'Louer', 'Rent', 'إيجار')
A('footer.allrent', 'Toutes les locations', 'All rentals', 'جميع الإيجارات')
A('footer.verified', 'Concessionnaires vérifiés', 'Verified dealers', 'الوكلاء الموثقون')
A('footer.join', 'Devenir partenaire', 'Become a partner', 'كن شريكًا')
A('footer.help', 'Aide', 'Help', 'مساعدة')
A('footer.safety', 'Conseils de sécurité', 'Safety tips', 'نصائح السلامة')
A('footer.contact', 'Contact', 'Contact', 'اتصل بنا')
A('footer.legal', 'Légal', 'Legal', 'قانوني')
A('footer.terms', 'Conditions d’utilisation', 'Terms of use', 'شروط الاستخدام')
A('footer.privacy', 'Confidentialité', 'Privacy', 'الخصوصية')
A('footer.notices', 'Mentions légales', 'Legal notices', 'إشعارات قانونية')
A('footer.proto', 'Prototype de conception, données de démonstration.', 'Design prototype, demo data.', 'نموذج تصميمي، بيانات تجريبية.')
A('proto.title', 'Contrôles du prototype', 'Prototype controls', 'عناصر التحكم في النموذج')
A('proto.note', 'Testez les trois niveaux de mouvement. Le niveau « Auto » suit votre appareil et la préférence « réduire les animations ».', 'Try the three motion tiers. "Auto" follows your device and the "reduce motion" preference.', 'جرّب مستويات الحركة الثلاثة. يتبع الوضع التلقائي جهازك وتفضيل تقليل الحركة.')
A('proto.motion', 'Mouvement', 'Motion', 'الحركة')
A('proto.auto', 'Auto', 'Auto', 'تلقائي')

# ---- AI panel
A('ai.fab', 'Assistant', 'Assistant', 'المساعد')
A('ai.open', 'Ouvrir l’assistant', 'Open the assistant', 'افتح المساعد')
A('ai.title', 'Assistant Wotoyi', 'Wotoyi assistant', 'مساعد ووتويي')
A('ai.notice', 'Recherche simple activée : l’assistant IA complet n’est pas configuré. Il comprend des critères (type, énergie, boîte, budget, ville) et ouvre la recherche filtrée.', 'Simple search enabled: the full AI assistant is not configured. It understands criteria (type, fuel, gearbox, budget, city) and opens the filtered search.', 'البحث البسيط مفعّل: المساعد الذكي الكامل غير مهيأ. يفهم المعايير (النوع والوقود وناقل الحركة والميزانية والمدينة) ويفتح البحث المصفّى.')
A('ai.examples', 'Exemples', 'Examples', 'أمثلة')
A('ai.placeholder', 'Décrivez le véhicule recherché…', 'Describe the vehicle you want…', 'صف المركبة التي تبحث عنها…')
A('ai.send', 'Envoyer', 'Send', 'إرسال')
A('ai.p.fr', 'Un SUV automatique moins de 15 millions à Dakar', 'Un SUV automatique moins de 15 millions à Dakar', 'Un SUV automatique moins de 15 millions à Dakar')
A('ai.p.en', 'A hybrid sedan under 18 million in Dakar', 'A hybrid sedan under 18 million in Dakar', 'A hybrid sedan under 18 million in Dakar')
A('ai.p.ar', 'سيارة دفع رباعي ديزل أوتوماتيك في داكار', 'سيارة دفع رباعي ديزل أوتوماتيك في داكار', 'سيارة دفع رباعي ديزل أوتوماتيك في داكار')
A('ai.p.wo', 'Wolof : en attente de relecture native', 'Wolof: awaiting native review', 'الولوف: بانتظار المراجعة')
A('ai.understood', 'Voici les critères compris :', 'Here are the criteria I understood:', 'هذه هي المعايير التي فهمتها:')
A('ai.nomatch', 'Je n’ai pas reconnu de critère. Essayez par exemple : « SUV automatique moins de 15 millions à Dakar ».', 'I did not recognise any criteria. Try for example: "automatic SUV under 15 million in Dakar".', 'لم أتعرف على أي معيار. جرّب مثلًا: «سيارة دفع رباعي ديزل أوتوماتيك في داكار».')
A('ai.see', 'Voir les résultats', 'See results', 'عرض النتائج')

# ---- hero + quick search
A('hero.overline', 'Marketplace automobile · Sénégal', 'Car marketplace · Senegal', 'سوق السيارات · السنغال')
A('hero.h1', 'Le showroom automobile du Sénégal, <em class="wy-em">en ligne</em>.', 'Senegal’s car showroom, <em class="wy-em">online</em>.', 'معرض السيارات في السنغال، <em class="wy-em">على الإنترنت</em>.')
A('hero.sub', 'Achetez ou louez un véhicule auprès de concessionnaires vérifiés, faites-le inspecter par un tiers indépendant, puis contactez le vendeur directement sur WhatsApp.', 'Buy or rent a vehicle from verified dealers, have it inspected by an independent third party, then contact the seller directly on WhatsApp.', 'اشترِ أو استأجر مركبة من وكلاء موثقين، واطلب فحصها من طرف مستقل، ثم تواصل مع البائع مباشرة عبر واتساب.')
A('hero.car.alt', 'Illustration provisoire d\u2019un véhicule gris argent', 'Placeholder illustration of a silver-grey vehicle', 'رسم مؤقت لمركبة رمادية فضية')
A('qs.title', 'Recherche rapide', 'Quick search', 'بحث سريع')
A('qs.offer', 'Type d’offre', 'Offer type', 'نوع العرض')
A('qs.buy', 'Acheter', 'Buy', 'شراء')
A('qs.rent', 'Louer', 'Rent', 'إيجار')
A('qs.go', 'Rechercher', 'Search', 'بحث')
A('qs.vehicle', 'véhicule', 'vehicle', 'مركبة')
A('qs.vehicles', 'véhicules', 'vehicles', 'مركبات')
A('qs.nl', 'Décrivez le véhicule recherché…', 'Describe the vehicle you want…', 'صف المركبة التي تبحث عنها…')
A('qs.nlhint', 'Assistant', 'Assistant', 'المساعد')
A('types.title', 'Types de véhicules', 'Vehicle types', 'أنواع المركبات')

# ---- filter labels (shared by home + search + vehicle)
for k, fr, en, ar in [
 ('f.make', 'Marque', 'Make', 'العلامة'), ('f.model', 'Modèle', 'Model', 'الطراز'), ('f.version', 'Version / finition', 'Trim', 'الفئة'),
 ('f.allmakes', 'Toutes les marques', 'All makes', 'كل العلامات'), ('f.allmodels', 'Tous les modèles', 'All models', 'كل الطرازات'),
 ('f.budgetmax', 'Budget max (FCFA)', 'Max budget (FCFA)', 'الميزانية القصوى (فرنك)'), ('f.budgetmonth', 'Budget max / mois (FCFA)', 'Max budget / month (FCFA)', 'الميزانية القصوى / شهر (فرنك)'),
 ('f.nolimit', 'Sans limite', 'No limit', 'بلا حد'), ('f.city', 'Ville', 'City', 'المدينة'), ('f.allcities', 'Toutes les villes', 'All cities', 'كل المدن'),
 ('f.any', 'Indifférent', 'Any', 'أي'), ('f.offer', 'Offre', 'Offer', 'العرض'), ('f.body', 'Carrosserie', 'Body type', 'نوع الهيكل'),
 ('f.price', 'Prix', 'Price', 'السعر'), ('f.year', 'Année', 'Year', 'السنة'), ('f.km', 'Kilométrage', 'Mileage', 'المسافة المقطوعة'),
 ('f.fuel', 'Énergie', 'Fuel', 'الوقود'), ('f.trans', 'Boîte', 'Gearbox', 'ناقل الحركة'), ('f.drive', 'Transmission', 'Drive', 'الدفع'),
 ('f.cc', 'Cylindrée (cm³)', 'Engine size (cc)', 'سعة المحرك (سم³)'), ('f.hp', 'Puissance (ch)', 'Power (hp)', 'القدرة (حصان)'),
 ('f.unknownexcl', 'Les annonces sans cette donnée sont exclues quand le critère est actif.', 'Listings without this data are excluded when the criterion is active.', 'تُستبعد الإعلانات التي تفتقر إلى هذه البيانات عند تفعيل المعيار.'),
 ('f.seats', 'Places', 'Seats', 'المقاعد'), ('f.doors', 'Portes', 'Doors', 'الأبواب'), ('f.color', 'Couleur extérieure', 'Exterior colour', 'اللون الخارجي'),
 ('f.cond', 'État', 'Condition', 'الحالة'), ('f.origin', 'Pays d’origine', 'Country of origin', 'بلد المنشأ'), ('f.customs', 'Statut douanier', 'Customs status', 'الوضع الجمركي'),
 ('f.owners', 'Propriétaires', 'Owners', 'عدد الملاك'), ('f.noacc', 'Sans accident déclaré', 'No accident declared', 'بلا حوادث مصرّح بها'),
 ('f.service', 'Carnet d’entretien', 'Service history', 'سجل الصيانة'), ('f.warr', 'Garantie', 'Warranty', 'ضمان'),
 ('f.insp', 'Inspecté par Wotoyi', 'Inspected by Wotoyi', 'تم فحصه عبر ووتويي'), ('f.vdealer', 'Concessionnaire vérifié', 'Verified dealer', 'وكيل موثق'), ('f.dealer', 'Concessionnaire', 'Dealer', 'الوكيل'),
 ('f.deliv', 'Livraison possible', 'Delivery available', 'التوصيل متاح'), ('f.fin', 'Financement possible', 'Financing available', 'التمويل متاح'), ('f.trade', 'Reprise acceptée', 'Trade-in accepted', 'استبدال مقبول'),
 ('f.eqsearch', 'Rechercher un équipement', 'Search equipment', 'ابحث عن تجهيز'), ('f.since', 'Publiée depuis', 'Posted within', 'نُشر خلال'),
 ('f.mediaNote', 'Toutes les annonces de la démo ont des photos. Les filtres vidéo et 360° apparaîtront avec les médias réels.', 'All demo listings have photos. Video and 360° filters will appear with real media.', 'جميع إعلانات التجربة لها صور. ستظهر مرشحات الفيديو و360° مع الوسائط الحقيقية.'),
 ('g.essential', 'Essentiel', 'Essentials', 'الأساسيات'), ('g.engine', 'Motorisation', 'Engine & drive', 'المحرك'), ('g.body', 'Carrosserie et intérieur', 'Body & interior', 'الهيكل والداخل'),
 ('g.state', 'État et historique', 'Condition & history', 'الحالة والسجل'), ('g.trust', 'Confiance et vendeur', 'Trust & seller', 'الثقة والبائع'), ('g.commercial', 'Conditions commerciales', 'Commercial terms', 'الشروط التجارية'),
 ('g.equip', 'Équipements', 'Equipment', 'التجهيزات'), ('g.media', 'Médias et date', 'Media & date', 'الوسائط والتاريخ')]:
    A(k, fr, en, ar)
for k, fr, en, ar in [('offer.both','Tous','All','الكل'),('offer.sale','Achat','Buy','شراء'),('offer.rent','Location','Rent','إيجار'),
 ('since.1','24 heures','24 hours','24 ساعة'),('since.7','7 jours','7 days','7 أيام'),('since.30','30 jours','30 days','30 يومًا')]: A(k, fr, en, ar)

# ---- vocab
for k, fr, en, ar in [
 ('body.suv','SUV','SUV','دفع رباعي (SUV)'),('body.berline','Berline','Sedan','سيدان'),('body.citadine','Citadine','City car','سيارة مدينة'),('body.4x4','4x4','4x4','4x4'),('body.pickup','Pick-up','Pick-up','بيك أب'),
 ('body.monospace','Monospace','MPV','ميني فان'),('body.utilitaire','Utilitaire','Van','نقل خفيف'),('body.eco','Hybride et électrique','Hybrid & electric','هجين وكهربائي'),
 ('fuel.essence','Essence','Petrol','بنزين'),('fuel.diesel','Diesel','Diesel','ديزل'),('fuel.hybride','Hybride','Hybrid','هجين'),('fuel.phev','Hybride rechargeable','Plug-in hybrid','هجين قابل للشحن'),('fuel.electrique','Électrique','Electric','كهربائي'),('fuel.gpl','GPL','LPG','غاز مسال'),
 ('trans.auto','Automatique','Automatic','أوتوماتيك'),('trans.manuelle','Manuelle','Manual','يدوي'),
 ('drive.fwd','Traction','Front-wheel drive','دفع أمامي'),('drive.rwd','Propulsion','Rear-wheel drive','دفع خلفي'),('drive.4x4','4x4','4x4','4x4'),('drive.awd','Intégrale','All-wheel drive','دفع كلي'),
 ('cond.neuf','Neuf','New','جديد'),('cond.occasion','Occasion','Used','مستعمل'),('cond.venant','Venant','Imported (venant)','مستورد (venant)'),
 ('customs.cleared','Dédouané','Customs cleared','مُخلّص جمركيًا'),('customs.transit','En transit','In transit','قيد العبور'),
 ('city.dakar','Dakar','Dakar','داكار'),('city.thies','Thiès','Thiès','تييس'),('city.saintlouis','Saint-Louis','Saint-Louis','سان لويس'),('city.mbour','Mbour','Mbour','مبور'),
 ('col.gris','Gris argent','Silver grey','رمادي فضي'),('col.bleuatl','Bleu atlantique','Atlantic blue','أزرق أطلسي'),('col.blancnacre','Blanc nacré','Pearl white','أبيض لؤلؤي'),('col.ambre','Ambre','Amber','عنبري'),('col.vertsauge','Vert sauge','Sage green','أخضر ميرمية'),('col.noirobs','Noir obsidienne','Obsidian black','أسود سبجي'),('col.rougerubis','Rouge rubis','Ruby red','أحمر ياقوتي'),
 ('eqcat.comfort','Confort','Comfort','الراحة'),('eqcat.safety','Sécurité','Safety','الأمان'),('eqcat.media','Multimédia','Multimedia','الوسائط'),('eqcat.exterior','Extérieur','Exterior','الخارج'),('eqcat.interior','Intérieur','Interior','الداخل'),
 ('eq.ac','Climatisation automatique','Automatic air conditioning','تكييف أوتوماتيك'),('eq.heated','Sièges chauffants','Heated seats','مقاعد مدفأة'),('eq.sunroof','Toit ouvrant','Sunroof','سقف مفتوح'),('eq.cruise','Régulateur de vitesse','Cruise control','مثبت السرعة'),('eq.keyless','Démarrage sans clé','Keyless start','تشغيل بدون مفتاح'),
 ('eq.airbags','Airbags','Airbags','وسائد هوائية'),('eq.abs','ABS','ABS','نظام ABS'),('eq.esp','Contrôle de stabilité (ESP)','Stability control (ESP)','نظام الثبات (ESP)'),('eq.camera','Caméra de recul','Rear camera','كاميرا خلفية'),('eq.sensors','Capteurs de stationnement','Parking sensors','حساسات ركن'),('eq.lane','Aide au maintien de voie','Lane-keeping assist','مساعد البقاء في المسار'),
 ('eq.screen','Écran tactile','Touchscreen','شاشة لمس'),('eq.carplay','Apple CarPlay / Android Auto','Apple CarPlay / Android Auto','Apple CarPlay / Android Auto'),('eq.bt','Bluetooth','Bluetooth','بلوتوث'),('eq.gps','Navigation GPS','GPS navigation','ملاحة GPS'),
 ('eq.alloy','Jantes alliage','Alloy wheels','جنوط معدنية'),('eq.led','Phares LED','LED headlights','مصابيح LED'),('eq.roofbars','Barres de toit','Roof bars','قضبان سقف'),('eq.leather','Sellerie cuir','Leather upholstery','تنجيد جلدي'),('eq.seats7','7 places','7 seats','7 مقاعد')]:
    A(k, fr, en, ar)

# ---- home sections
A('feat.over', 'À la une', 'Featured', 'مختارات')
A('feat.title', 'Véhicules à la une', 'Featured vehicles', 'مركبات مميزة')
A('marq.title', 'Marques disponibles dans la démo', 'Makes available in the demo', 'العلامات المتوفرة في التجربة')
A('trust.over', 'Acheter en confiance', 'Buy with confidence', 'اشترِ بثقة')
A('trust.title', 'Trois étapes pour réduire le risque', 'Three steps to reduce risk', 'ثلاث خطوات لتقليل المخاطر')
A('trust.s1.t', 'Concessionnaires vérifiés', 'Verified dealers', 'وكلاء موثقون')
A('trust.s1.p', 'Chaque concessionnaire passe un contrôle de ses justificatifs d’entreprise, de l’identité de son responsable et de son adresse avant d’afficher le badge. Le badge concerne le professionnel, pas chaque véhicule.', 'Each dealer goes through a check of its business documents, manager identity and address before getting the badge. The badge covers the business, not each vehicle.', 'يخضع كل وكيل لفحص مستندات شركته وهوية مسؤوله وعنوانه قبل منحه الشارة. الشارة تخص المهني وليس كل مركبة.')
A('trust.s2.t', 'Inspection Wotoyi', 'Wotoyi inspection', 'فحص ووتويي')
A('trust.s2.p', 'Un inspecteur indépendant du vendeur contrôle le véhicule et remet un rapport daté. Vous décidez en connaissance de cause.', 'An inspector independent of the seller checks the vehicle and issues a dated report. You decide with the facts in hand.', 'يفحص مفتش مستقل عن البائع المركبة ويسلّم تقريرًا مؤرخًا. تقرر وأنت على علم بالوقائع.')
A('trust.s3.t', 'Contact direct sur WhatsApp', 'Direct contact on WhatsApp', 'تواصل مباشر عبر واتساب')
A('trust.s3.p', 'Un message prérempli avec la référence de l’annonce vous met en relation avec le concessionnaire, sans intermédiaire.', 'A pre-filled message with the listing reference connects you to the dealer, with no middleman.', 'رسالة جاهزة تحمل رقم الإعلان تربطك بالوكيل دون وسيط.')
A('badge.verified', 'Concessionnaire vérifié', 'Verified dealer', 'وكيل موثق')
A('badge.inspected', 'Inspecté par Wotoyi', 'Inspected by Wotoyi', 'تم فحصه عبر ووتويي')
A('badge.sponsored', 'Sponsorisé', 'Sponsored', 'ممول')
A('badge.venant', 'Venant', 'Imported', 'مستورد')
A('badge.cleared', 'Dédouané', 'Cleared', 'مُخلّص')
A('rent.over', 'Location', 'Rental', 'إيجار')
A('rent.title', 'À louer maintenant', 'Available to rent', 'متاح للإيجار')
A('rent.all', 'Toutes les locations', 'All rentals', 'جميع الإيجارات')
A('price.rentday', '{p} / jour', '{p} / day', '{p} / يوم')
A('price.rentmonth', '{p} / mois', '{p} / month', '{p} / شهر')
A('serv.over', 'Services', 'Services', 'خدمات')
A('serv.title', 'Entretien, location avec chauffeur, import', 'Maintenance, chauffeur hire, import', 'صيانة، إيجار مع سائق، استيراد')
A('serv.s1.t', 'Entretien et mécanique', 'Maintenance and mechanics', 'صيانة وميكانيكا'); S['serv.s1.p'] = ('Vidange, freinage, climatisation et diagnostic électronique dans un atelier partenaire.', 'Oil change, brakes, air conditioning and electronic diagnostics at a partner workshop.', 'تغيير الزيت والفرامل والتكييف والتشخيص الإلكتروني في ورشة شريكة.')
A('serv.s2.t', 'Location avec chauffeur', 'Chauffeur-driven hire', 'إيجار مع سائق'); S['serv.s2.p'] = ('Transferts aéroport, déplacements professionnels et événements, avec un chauffeur du concessionnaire.', 'Airport transfers, business trips and events with a dealer-provided chauffeur.', 'نقل من المطار ورحلات عمل وفعاليات مع سائق من الوكيل.')
A('serv.s3.t', 'Import et dédouanement', 'Import and customs clearance', 'استيراد وتخليص جمركي'); S['serv.s3.p'] = ('Accompagnement pour faire venir un véhicule et suivre les formalités douanières.', 'Support to bring in a vehicle and follow customs formalities.', 'مرافقة لاستيراد مركبة ومتابعة الإجراءات الجمركية.')
A('serv.s4.t', 'Detailing et lavage', 'Detailing and washing', 'تلميع وغسيل'); S['serv.s4.p'] = ('Nettoyage intérieur et extérieur, polissage et protection de la carrosserie.', 'Interior and exterior cleaning, polishing and body protection.', 'تنظيف داخلي وخارجي وتلميع وحماية الهيكل.')
A('insp.over', 'Inspection indépendante', 'Independent inspection', 'فحص مستقل')
A('insp.title', 'Faites inspecter avant d’acheter', 'Have it inspected before you buy', 'اطلب الفحص قبل الشراء')
A('insp.lead', 'Un inspecteur sans lien avec le vendeur examine le véhicule selon une liste de contrôle et vous remet un rapport PDF daté, avec photos et résultats par point.', 'An inspector with no tie to the seller examines the vehicle against a checklist and gives you a dated PDF report with photos and per-item results.', 'يفحص مفتش لا علاقة له بالبائع المركبة وفق قائمة تحقق ويسلّمك تقريرًا بصيغة PDF مؤرخًا مع صور ونتائج لكل بند.')
A('insp.m1', 'Extérieur et intérieur : carrosserie, peinture, vitres, pneus, usure', 'Exterior and interior: body, paint, glass, tyres, wear', 'الخارج والداخل: الهيكل والطلاء والزجاج والإطارات والتآكل')
A('insp.m2', 'Mécanique : moteur, boîte, suspension, freins, fuites', 'Mechanics: engine, gearbox, suspension, brakes, leaks', 'الميكانيكا: المحرك وناقل الحركة والتعليق والفرامل والتسريبات')
A('insp.m3', 'Diagnostic électronique : codes défaut OBD', 'Electronic diagnostics: OBD fault codes', 'التشخيص الإلكتروني: رموز أعطال OBD')
A('insp.m4', 'Cohérence du kilométrage : indicateurs uniquement', 'Mileage consistency: indicators only', 'اتساق المسافة المقطوعة: مؤشرات فقط')
A('insp.m5', 'Documents présentés : carte grise, pièces douanières', 'Documents presented: registration, customs papers', 'المستندات المقدمة: الرخصة والأوراق الجمركية')
A('insp.m6', 'Essai routier, lorsqu’il est possible et autorisé', 'Road test, when possible and authorised', 'تجربة قيادة عند الإمكان والسماح')
A('insp.disclaimer', 'Le rapport décrit l’état observé à la date de l’inspection avec les méthodes listées. Il ne garantit ni l’authenticité du kilométrage, ni l’absence d’accident passé, ni l’absence de défauts cachés.', 'The report describes the condition observed on the inspection date with the listed methods. It does not guarantee mileage authenticity, absence of past accidents or absence of hidden defects.', 'يصف التقرير الحالة الملاحظة في تاريخ الفحص بالطرق المذكورة. ولا يضمن صحة المسافة المقطوعة ولا خلو المركبة من حوادث سابقة ولا من عيوب خفية.')
A('insp.cta', 'Commander une inspection', 'Order an inspection', 'اطلب فحصًا')
A('insp.toast', 'Démo : la commande d’inspection sera disponible avec la plateforme.', 'Demo: inspection ordering will ship with the platform.', 'تجريبي: سيتوفر طلب الفحص مع المنصة.')
A('insp.pricenote', 'Forfaits et tarifs définis dans les réglages de la marketplace : aucun tarif n’est fixé dans cette démo.', 'Packages and prices are set in the marketplace settings: no price is set in this demo.', 'تُحدَّد الباقات والأسعار في إعدادات السوق: لا يوجد سعر في هذه التجربة.')
A('insp.sample', 'Exemple illustratif', 'Illustrative example', 'مثال توضيحي')
A('insp.r.title', 'Rapport d’inspection', 'Inspection report', 'تقرير الفحص')
A('insp.r.meta', 'Réf. et date : exemple', 'Ref. and date: example', 'المرجع والتاريخ: مثال')
A('insp.r.i1', 'Carrosserie et peinture', 'Body and paint', 'الهيكل والطلاء'); A('insp.r.i2', 'Freinage', 'Braking', 'الفرامل'); A('insp.r.i3', 'Pneus', 'Tyres', 'الإطارات'); A('insp.r.i4', 'Diagnostic OBD', 'OBD diagnostics', 'تشخيص OBD'); A('insp.r.i5', 'Kilométrage (indicateurs)', 'Mileage (indicators)', 'المسافة المقطوعة (مؤشرات)')
A('insp.r.ok', 'Conforme', 'OK', 'سليم'); A('insp.r.warn', 'À surveiller', 'Watch', 'يحتاج متابعة'); A('insp.r.info', 'Indicateur', 'Indicator', 'مؤشر')
A('insp.r.foot', 'Mise en page indicative : le rapport réel suit le modèle validé avec les inspecteurs.', 'Indicative layout: the real report follows the template validated with inspectors.', 'تخطيط إرشادي: يتبع التقرير الفعلي النموذج المعتمد مع المفتشين.')
A('deal.over', 'Concessionnaires', 'Dealers', 'الوكلاء')
A('deal.title', 'Concessionnaires vérifiés', 'Verified dealers', 'وكلاء موثقون')
A('deal.listing', 'annonce', 'listing', 'إعلان'); A('deal.listings', 'annonces', 'listings', 'إعلانات')
A('deal.see', 'Voir le concessionnaire', 'View dealer', 'عرض الوكيل')
A('deal.open', 'Ouvert maintenant', 'Open now', 'مفتوح الآن'); A('deal.closed', 'Fermé', 'Closed', 'مغلق')
A('hours.range', 'Lun–Sam {a}h–{b}h', 'Mon–Sat {a}:00–{b}:00', 'الاثنين–السبت {a}:00–{b}:00')
A('hours.demo', 'horaires de démo', 'demo hours', 'ساعات تجريبية')
A('aiS.over', 'Assistant IA', 'AI assistant', 'المساعد الذكي')
A('aiS.title', 'Décrivez, l’assistant filtre', 'Describe it, the assistant filters', 'صِف ما تريد ويقوم المساعد بالتصفية')
A('aiS.lead', 'Écrivez comme vous parleriez à un vendeur, en français, anglais ou arabe. Le wolof arrivera après relecture par des locuteurs natifs.', 'Write the way you would talk to a salesperson, in French, English or Arabic. Wolof will follow after native-speaker review.', 'اكتب كما تتحدث مع بائع، بالفرنسية أو الإنجليزية أو العربية. سيأتي الولوف بعد مراجعة متحدثين أصليين.')
A('aiS.c1', 'Recherche en langage naturel', 'Natural-language search', 'بحث بلغة طبيعية'); A('aiS.c2', 'Comparer des véhicules', 'Compare vehicles', 'مقارنة المركبات'); A('aiS.c3', 'Expliquer une fiche technique ou une inspection', 'Explain specs or an inspection', 'شرح المواصفات أو الفحص'); A('aiS.c4', 'Passer le relais au concessionnaire sur WhatsApp', 'Hand over to the dealer on WhatsApp', 'التحويل إلى الوكيل عبر واتساب')
A('fig.title', 'La démo en chiffres', 'The demo in numbers', 'التجربة بالأرقام')
A('fig.v', 'Véhicules de la démo', 'Demo vehicles', 'مركبات التجربة'); A('fig.d', 'Concessionnaires vérifiés (démo)', 'Verified dealers (demo)', 'وكلاء موثقون (تجربة)'); A('fig.c', 'Villes couvertes (démo)', 'Cities covered (demo)', 'مدن مشمولة (تجربة)')
A('fig.note', 'Ces nombres sont calculés sur les données de démonstration de ce prototype, pas sur une activité réelle. En production, seuls des comptages réels seront affichés.', 'These numbers are computed from this prototype’s demo data, not from real activity. In production only real counts will be shown.', 'هذه الأرقام محسوبة من البيانات التجريبية لهذا النموذج وليست نشاطًا حقيقيًا. في الإنتاج ستُعرض الأعداد الحقيقية فقط.')
A('dcta.over', 'Pour les professionnels', 'For professionals', 'للمحترفين')
A('dcta.title', 'Vous êtes concessionnaire ?', 'Are you a dealer?', 'هل أنت وكيل سيارات؟')
A('dcta.b1.t', 'Votre propre site web', 'Your own website', 'موقعك الخاص'); A('dcta.b1.p', 'Domaine, hébergement et marque à vous.', 'Your domain, hosting and brand.', 'نطاقك واستضافتك وعلامتك.')
A('dcta.b2.t', 'Publication automatique sur Wotoyi', 'Automatic publishing on Wotoyi', 'نشر تلقائي على ووتويي'); A('dcta.b2.p', 'Un véhicule saisi une fois apparaît sur les deux.', 'Enter a vehicle once and it appears on both.', 'أدخل المركبة مرة واحدة فتظهر في الاثنين.')
A('dcta.b3.t', 'CRM et IA', 'CRM and AI', 'إدارة العملاء والذكاء الاصطناعي'); A('dcta.b3.p', 'Prospects, tâches et réponses assistées au même endroit.', 'Leads, tasks and assisted replies in one place.', 'العملاء والمهام والردود المساعَدة في مكان واحد.')
A('dcta.cta', 'Découvrir Wotoyi Pro', 'Discover Wotoyi Pro', 'اكتشف Wotoyi Pro')
A('dcta.mock', 'Maquette schématique de Wotoyi Pro, sans données', 'Schematic mock-up of Wotoyi Pro, no data', 'نموذج تخطيطي لـ Wotoyi Pro بدون بيانات')
A('dcta.k1', 'Nouveaux prospects', 'New leads', 'عملاء جدد'); A('dcta.k2', 'Tâches du jour', 'Tasks today', 'مهام اليوم'); A('dcta.k3', 'Véhicules à mettre à jour', 'Vehicles to update', 'مركبات للتحديث')
A('recent.title', 'Consultés récemment', 'Recently viewed', 'شوهدت مؤخرًا')

# ---- search
A('search.title', 'Rechercher un véhicule', 'Search vehicles', 'البحث عن مركبة')
A('search.kw', 'Marque, modèle, ville…', 'Make, model, city…', 'العلامة، الطراز، المدينة…')
A('search.filters', 'Filtres', 'Filters', 'المرشحات')
A('search.sort', 'Trier par', 'Sort by', 'ترتيب حسب')
A('sort.relevance', 'Pertinence', 'Relevance', 'الأهمية'); A('sort.priceAsc', 'Prix croissant', 'Price: low to high', 'السعر: الأقل أولًا'); A('sort.priceDesc', 'Prix décroissant', 'Price: high to low', 'السعر: الأعلى أولًا'); A('sort.year', 'Année récente', 'Newest year', 'الأحدث سنة'); A('sort.km', 'Kilométrage croissant', 'Mileage: low to high', 'المسافة: الأقل أولًا'); A('sort.new', 'Plus récentes', 'Latest listings', 'الأحدث نشرًا')
A('search.view', 'Affichage', 'View', 'العرض'); A('search.grid', 'Grille', 'Grid', 'شبكة'); A('search.list', 'Liste', 'List', 'قائمة')
A('search.save', 'Enregistrer la recherche', 'Save search', 'احفظ البحث'); A('search.saved', 'Recherche enregistrée', 'Search saved', 'تم حفظ البحث')
A('search.savedToast', 'Recherche enregistrée sur cet appareil (démo). En production, elle sera liée au compte avec alertes.', 'Search saved on this device (demo). In production it will be tied to the account with alerts.', 'تم حفظ البحث على هذا الجهاز (تجربة). في الإنتاج سيرتبط بالحساب مع تنبيهات.')
A('search.unsavedToast', 'Recherche retirée.', 'Search removed.', 'تمت إزالة البحث.')
A('search.active', 'Filtres actifs', 'Active filters', 'المرشحات النشطة'); A('search.clear', 'Tout effacer', 'Clear all', 'مسح الكل'); A('search.remove', 'Retirer ce filtre', 'Remove this filter', 'إزالة هذا المرشح')
A('search.result', 'résultat', 'result', 'نتيجة'); A('search.results', 'résultats', 'results', 'نتائج'); A('search.see', 'Voir', 'See', 'عرض'); A('search.pages', 'Pagination', 'Pagination', 'ترقيم الصفحات')
A('empty.title', 'Aucun véhicule ne correspond à ces critères', 'No vehicle matches these criteria', 'لا توجد مركبة تطابق هذه المعايير')
A('empty.p', 'Élargissez la recherche en retirant un critère, ou laissez-nous vous prévenir quand un véhicule correspondant sera publié.', 'Broaden the search by removing a criterion, or let us notify you when a matching vehicle is published.', 'وسّع البحث بإزالة معيار، أو دعنا ننبهك عند نشر مركبة مطابقة.')
A('empty.remove', 'Retirer', 'Remove', 'إزالة'); A('empty.save', 'Enregistrer la recherche pour être alerté', 'Save the search to get alerts', 'احفظ البحث لتتلقى تنبيهات'); A('empty.ai', 'Demander à l’assistant', 'Ask the assistant', 'اسأل المساعد')

# ---- vehicle
for k, fr, en, ar in [
 ('v.media','Médias du véhicule','Vehicle media','وسائط المركبة'),('v.photos','Photos','Photos','صور'),('v.fullscreen','Plein écran','Full screen','ملء الشاشة'),('v.illus','Illustration provisoire','Placeholder illustration','رسم مؤقت'),
 ('v.view','Vue {n}','View {n}','المشهد {n}'),('v.placeholdernote','Les images sont des illustrations provisoires : les rendus studio et les photos réelles remplaceront ces visuels.','Images are placeholder illustrations: studio renders and real photos will replace them.','الصور رسوم مؤقتة: ستحل محلها صور الاستوديو والصور الحقيقية.'),
 ('v.v360','Visionneuse 360 degrés','360-degree viewer','عارض 360 درجة'),('v.rotate','Tourner','Rotate','تدوير'),('v.v360hint','Faites glisser, ou utilisez les flèches du clavier','Drag, or use the keyboard arrows','اسحب أو استخدم أسهم لوحة المفاتيح'),
 ('v.v360note','Maquette : la vraie visionneuse affiche une séquence de 24 à 36 images téléversées par le concessionnaire. Ici, la rotation est simulée avec une seule illustration.','Mock-up: the real viewer shows a 24 to 36 image sequence uploaded by the dealer. Here the rotation is simulated with a single illustration.','نموذج: يعرض العارض الحقيقي سلسلة من 24 إلى 36 صورة يرفعها الوكيل. هنا يُحاكى الدوران برسم واحد.'),
 ('v.neg','Prix négociable','Price negotiable','السعر قابل للتفاوض'),('v.rental','Location','Rental','إيجار'),('v.negotiable','Prix négociable','Price negotiable','السعر قابل للتفاوض'),
 ('v.contact','Contacter le vendeur','Contact the seller','تواصل مع البائع'),('v.call','Appeler','Call','اتصال'),('v.message','Message','Message','رسالة'),('v.reqinsp','Demander une inspection','Request an inspection','اطلب فحصًا'),
 ('v.askai','Demander à l’IA','Ask the AI','اسأل الذكاء الاصطناعي'),('v.share','Partager','Share','مشاركة'),('v.compare','Comparer','Compare','مقارنة'),('v.ref','Référence','Reference','المرجع'),
 ('v.specs','Caractéristiques','Specifications','المواصفات'),('v.specnote','Données de démonstration non contractuelles : les groupes sans donnée sont masqués.','Demo data, not binding: groups without data are hidden.','بيانات تجريبية غير ملزمة: تُخفى المجموعات التي لا بيانات فيها.'),
 ('v.desc','Description','Description','الوصف'),('v.descdemo','Texte de démonstration rédigé en français.','Demo text written in French.','نص تجريبي مكتوب بالفرنسية.'),
 ('v.descauto','Texte du concessionnaire en français. La traduction automatique sera fournie par l’assistant IA lorsqu’il est configuré, avec un lien « voir l’original ».','Dealer text in French. Automatic translation will be provided by the AI assistant when configured, with a "view original" link.','نص الوكيل بالفرنسية. ستوفر الترجمة الآلية عبر المساعد الذكي عند تهيئته مع رابط «عرض الأصل».'),
 ('v.insp','Inspection','Inspection','الفحص'),('v.inspdone','Ce véhicule a fait l’objet d’une inspection Wotoyi (démo). Le rapport PDF daté, avec photos et résultats par point, est consultable par l’acheteur.','This vehicle underwent a Wotoyi inspection (demo). The dated PDF report, with photos and per-item results, is available to the buyer.','خضعت هذه المركبة لفحص ووتويي (تجربة). يتاح للمشتري تقرير PDF مؤرخ مع صور ونتائج لكل بند.'),
 ('v.pdf','Consulter le rapport (PDF)','View the report (PDF)','عرض التقرير (PDF)'),('v.pdftoast','Démo : aucun rapport réel n’existe pour cette annonce.','Demo: no real report exists for this listing.','تجريبي: لا يوجد تقرير حقيقي لهذا الإعلان.'),
 ('v.inspnone','Cette annonce n’a pas encore été inspectée. Vous pouvez commander une inspection indépendante avant de vous décider.','This listing has not been inspected yet. You can order an independent inspection before you decide.','لم يُفحص هذا الإعلان بعد. يمكنك طلب فحص مستقل قبل أن تقرر.'),
 ('v.insptoast','Démo : la demande d’inspection sera disponible avec la plateforme.','Demo: inspection requests will ship with the platform.','تجريبي: سيتوفر طلب الفحص مع المنصة.'),
 ('v.dealer','Concessionnaire','Dealer','الوكيل'),('v.route','Itinéraire','Directions','الاتجاهات'),('v.similar','Véhicules similaires','Similar vehicles','مركبات مشابهة'),
 ('v.safety','Conseils de sécurité','Safety tips','نصائح السلامة'),('v.safe1','Ne payez jamais avant d’avoir vu le véhicule et ses papiers.','Never pay before you have seen the vehicle and its papers.','لا تدفع قبل أن ترى المركبة وأوراقها.'),
 ('v.safe2','Faites inspecter le véhicule par un tiers indépendant.','Have the vehicle inspected by an independent third party.','اطلب فحص المركبة من طرف مستقل.'),('v.safe3','Retrouvez le vendeur chez le concessionnaire, dans un lieu public.','Meet the seller at the dealership, in a public place.','قابل البائع في معرض الوكيل أو في مكان عام.'),
 ('v.report','Signaler l’annonce','Report this listing','الإبلاغ عن الإعلان'),('v.reporttoast','Démo : le signalement sera disponible avec la plateforme.','Demo: reporting will ship with the platform.','تجريبي: سيتوفر الإبلاغ مع المنصة.'),
 ('v.comparetoast','Démo : la comparaison sera disponible avec la plateforme.','Demo: comparison will ship with the platform.','تجريبي: ستتوفر المقارنة مع المنصة.'),
 ('wa.msg','Bonjour, je suis intéressé par {name} (réf. {ref}) vu sur Wotoyi. Est-il toujours disponible ?','Hello, I am interested in {name} (ref. {ref}) seen on Wotoyi. Is it still available?','مرحبًا، أنا مهتم بـ {name} (المرجع {ref}) الذي رأيته على ووتويي. هل ما زال متاحًا؟'),
 ('wa.toast','Démo : le bouton ouvrira WhatsApp avec le numéro du concessionnaire et ce message prérempli :','Demo: the button will open WhatsApp with the dealer’s number and this pre-filled message:','تجريبي: سيفتح الزر واتساب برقم الوكيل وهذه الرسالة الجاهزة:'),
 ('call.toast','Démo : le numéro du concessionnaire n’est pas défini dans les données de démonstration.','Demo: the dealer’s number is not defined in the demo data.','تجريبي: رقم الوكيل غير محدد في البيانات التجريبية.'),
 ('share.copied','Lien copié.','Link copied.','تم نسخ الرابط.'),
 ('lead.title','Envoyer un message au concessionnaire','Send a message to the dealer','أرسل رسالة إلى الوكيل'),('lead.name','Nom','Name','الاسم'),('lead.phone','Téléphone','Phone','الهاتف'),('lead.msg','Message','Message','الرسالة'),
 ('lead.note','Votre message est transmis au concessionnaire. Aucun envoi réel dans ce prototype.','Your message is passed to the dealer. Nothing is actually sent in this prototype.','تُرسل رسالتك إلى الوكيل. لا يُرسل شيء فعليًا في هذا النموذج.'),('lead.cancel','Annuler','Cancel','إلغاء'),('lead.send','Envoyer','Send','إرسال'),
 ('lead.toast','Démo : message non envoyé (prototype).','Demo: message not sent (prototype).','تجريبي: لم تُرسل الرسالة (نموذج).'),
 ('fav.add','Ajouter aux favoris','Add to favourites','أضف إلى المفضلة'),('fav.remove','Retirer des favoris','Remove from favourites','إزالة من المفضلة'),
 ('fav.count','{n} favori(s) enregistré(s) sur cet appareil (démo).','{n} favourite(s) saved on this device (demo).','{n} مفضلة محفوظة على هذا الجهاز (تجربة).'),
 ('img.alt','Illustration provisoire : {name}','Placeholder illustration: {name}','رسم مؤقت: {name}'),
 ('sg.overview','Aperçu','Overview','نظرة عامة'),('sg.engine','Moteur et performances','Engine & performance','المحرك والأداء'),('sg.cons','Consommation et environnement','Consumption & environment','الاستهلاك والبيئة'),('sg.dims','Dimensions et capacité','Dimensions & capacity','الأبعاد والسعة'),
 ('sg.equip','Équipements','Equipment','التجهيزات'),('sg.history','Historique et documents','History & documents','السجل والمستندات'),('sg.commercial','Conditions commerciales','Commercial terms','الشروط التجارية'),('sg.trust','Confiance','Trust','الثقة'),
 ('sg.privacy','Le numéro de châssis (VIN) et la plaque ne sont jamais affichés publiquement.','The VIN and plate number are never shown publicly.','لا يُعرض رقم الهيكل (VIN) ولا اللوحة علنًا.'),
 ('sg.declared','Information déclarée par le vendeur, non vérifiée par Wotoyi.','Information declared by the seller, not verified by Wotoyi.','معلومة صرّح بها البائع ولم تتحقق منها ووتويي.'),
 ('sg.posted','Publication','Posted','النشر'),('sg.postedago','il y a {n} jour(s)','{n} day(s) ago','قبل {n} يوم'),
 ('spec.colour','Couleur','Colour','اللون'),('spec.cc','Cylindrée','Engine size','سعة المحرك'),('spec.cyl','Cylindres','Cylinders','الأسطوانات'),('spec.hp','Puissance','Power','القدرة'),('unit.hp','ch','hp','حصان'),('spec.torque','Couple','Torque','عزم الدوران'),('spec.gears','Rapports','Gears','السرعات'),
 ('spec.euro','Norme antipollution','Emissions standard','معيار الانبعاثات'),('spec.comb','Consommation mixte','Combined consumption','الاستهلاك المختلط'),('spec.length','Longueur','Length','الطول'),('spec.width','Largeur','Width','العرض'),('spec.height','Hauteur','Height','الارتفاع'),('spec.wheelbase','Empattement','Wheelbase','قاعدة العجلات'),('spec.tank','Réservoir','Fuel tank','خزان الوقود')]:
    A(k, fr, en, ar)
