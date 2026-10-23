<?php
/**
 * विजेता पोर्टल — UDISE+ SDMS एक्सेल / CSV मधून विद्यार्थी थेट आयात (Smart UDISE+ Student Importer)
 * Auto-transliterates English names to Marathi Unicode, allows live inline edits,
 * supports mobile responsive cards + table views, and saves all classes with zero max_input_vars truncation.
 */
@ini_set('max_input_vars', '20000');
@ini_set('memory_limit', '256M');

$page_title = 'UDISE+ एक्सेल मधून विद्यार्थी आयात (Auto Marathi)';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/cce_functions.php';
require_once __DIR__ . '/../../modules/lib/module_helpers.php';
require_once __DIR__ . '/../../includes/demo_data.php';
requireLogin();

$db = getDB();
$school_id = (int)$_SESSION['school_id'];
$school = getSchool();
$year = academic_year();
$student_quota = getSchoolStudentQuota($school_id);

// =========================================================================
// 1. MARATHI TRANSLITERATION ENGINE
// =========================================================================
function udise_transliterate_name(string $fullName): string
{
    static $dict = null;
    if ($dict === null) {
        $dict = [
            // First Names (मुले व मुली)
            'AARADHYA' => 'आराध्या', 'ARADHYA' => 'आराध्या',
            'AARAV' => 'आरव', 'ARAV' => 'आरव',
            'AMRUTA' => 'अमृता', 'AMRITA' => 'अमृता',
            'ANAYA' => 'अनाया', 'ANANYA' => 'अनन्या', 'ANNYA' => 'अन्या',
            'ANVIKA' => 'अन्विका', 'ANVI' => 'अन्वी',
            'ANUSHKA' => 'अनुष्का', 'ANVITA' => 'अन्विता',
            'GAURI' => 'गौरी', 'GOWRI' => 'गौरी', 'GOURI' => 'गौरी',
            'OVI' => 'ओवी', 'OWI' => 'ओवी',
            'RAJNANDINI' => 'राजनंदिनी', 'RUDRA' => 'रुद्र',
            'SADIKSHA' => 'सादिक्षा', 'SAMIKSHA' => 'समीक्षा',
            'SANAVI' => 'सानवी', 'SAANVI' => 'सानवी',
            'SHIVAM' => 'शिवम', 'SHLOK' => 'श्लोक',
            'SHUBHRA' => 'शुभ्रा', 'SWARALI' => 'स्वराली', 'SWARA' => 'स्वरा',
            'VAIDEHI' => 'वैदेही', 'VEDIKA' => 'वेदिका',
            'AROHI' => 'आरोही', 'AAROHI' => 'आरोही',
            'ASHUTA' => 'अशुता',
            'KARTIK' => 'कार्तिक', 'KARTIKEY' => 'कार्तिकेय', 'KARTIKI' => 'कार्तिकी',
            'MRUNAL' => 'मृणाल',
            'OM' => 'ओम', 'OMKAR' => 'ओंकार', 'OMKARINI' => 'ओंकारिणी',
            'PARI' => 'परी',
            'PRATIK' => 'प्रतीक', 'PRATIKSHA' => 'प्रतीक्षा',
            'SANYOGITA' => 'संयोगिता',
            'SHAURYA' => 'शौर्य', 'SHOURYA' => 'शौर्य',
            'SONIYA' => 'सोनिया', 'SONIA' => 'सोनिया',
            'VAIBHAVI' => 'वैभवी', 'VAIBHAV' => 'वैभव',
            'VIDISHA' => 'विदिशा', 'VIDHI' => 'विधी',
            'ALMIRA' => 'अल्मिरा', 'DISHA' => 'दिशा',
            'MAYURI' => 'मयुरी', 'SHRADDHA' => 'श्रद्धा',
            'SHRAVANI' => 'श्रावणी', 'SHRISHA' => 'श्रीषा', 'SHRUTI' => 'श्रुती',
            'SWARANJALI' => 'स्वरांजली', 'UDAL' => 'उदल',
            'ABHISHRI' => 'अभिश्री', 'AVISHKA' => 'आविष्का',
            'DIKSHITA' => 'दीक्षिता', 'DIVYA' => 'दिव्या', 'DIYA' => 'दिया',
            'NEHA' => 'नेहा', 'PRACHI' => 'प्राची',
            'PRANITA' => 'प्रणिता', 'RIYA' => 'रिया', 'RHEA' => 'रिया',
            'SANJEEVANI' => 'संजीवनी', 'SANJIVANI' => 'संजीवनी',
            'SARTHAK' => 'सार्थक', 'SHIVANYA' => 'शिवान्या',
            'SHREYA' => 'श्रेया', 'SHRITEJ' => 'श्रीतेज', 'SHRIRAJ' => 'श्रीराज',
            'SIMA' => 'सीमा', 'SEEMA' => 'सीमा',
            'SRUSHTI' => 'सृष्टी', 'SHRUSHTI' => 'सृष्टी',
            'TANVI' => 'तन्वी', 'VIGHNESH' => 'विघ्नेश',
            'AADARSH' => 'आदर्श', 'ADARSH' => 'आदर्श',
            'AKSHARA' => 'अक्षरा', 'AKSHATA' => 'अक्षता',
            'ANJALI' => 'अंजली', 'DARSHAN' => 'दर्शन',
            'DHANSHRI' => 'धनश्री', 'DHANASHRI' => 'धनश्री',
            'DNYANESHWARI' => 'ज्ञानेश्वरी', 'HARSHVARDHAN' => 'हर्षवर्धन',
            'ISHWARI' => 'ईश्वरी', 'JIVEEKA' => 'जीविका',
            'MALIK' => 'मलिक', 'PRERANA' => 'प्रेरणा',
            'RAJ' => 'राज', 'SANIDHYA' => 'सानिध्य',
            'SANVI' => 'सान्वी', 'SHARAYU' => 'शरयू',
            'SHREYAS' => 'श्रेयस', 'SHRIKRUSHNA' => 'श्रीकृष्ण',
            'SHRISHANT' => 'श्रीशांत', 'SHRVAN' => 'श्रावण', 'SHRAVAN' => 'श्रावण',
            'SHWETA' => 'श्वेता', 'SIDDARTH' => 'सिद्धार्थ', 'SIDDHARTH' => 'सिद्धार्थ',
            'SIDDHESH' => 'सिद्धेश', 'SIDDHI' => 'सिद्धी',
            'SOHAM' => 'सोहम', 'VEDANT' => 'वेदांत', 'VED' => 'वेद',
            'YASH' => 'यश', 'ANISH' => 'अनिश',
            'ARAMAN' => 'अरमान', 'ARMAN' => 'अरमान',
            'KISMAT' => 'किस्मत', 'NEHARIKA' => 'निहारिका',
            'PARTH' => 'पार्थ', 'PRATHMESH' => 'प्रथमेश',
            'RADHIKA' => 'राधिका', 'SHARVIL' => 'शार्विल',
            'SUJAY' => 'सुजय', 'SURAJ' => 'सूरज',
            'VIHAN' => 'विहान', 'ABOLI' => 'अबोली',
            'ARYAN' => 'आर्यन', 'AYODHYA' => 'अयोध्या',
            'BABALU' => 'बबलू', 'DEVENDRA' => 'देवेंद्र',
            'DIKSHA' => 'दीक्षा', 'MINAKSHI' => 'मीनाक्षी',
            'PAYAL' => 'पायल', 'PRADNYA' => 'प्रज्ञा',
            'PRANJAL' => 'प्रांजल', 'ROHAN' => 'रोहन',
            'SAMARTH' => 'समर्थ', 'SANGRAM' => 'संग्राम',
            'SANJANA' => 'संजना', 'SANSKRUTI' => 'संस्कृती',
            'SHALMALI' => 'शाल्मली', 'SWAPNALI' => 'स्वप्नाली',
            'TANISHKA' => 'तनिष्का', 'TANUJA' => 'तनुजा',
            'TEJASWINI' => 'तेजस्विनी', 'VARAD' => 'वरद',
            'VIRANGANA' => 'वीरांगना', 'VIKRAM' => 'विक्रम',
            'DHIRAJ' => 'धीरज',

            // Parents & Middle Names
            'SUNIL' => 'सुनील', 'SATISH' => 'सतीश', 'YOGESH' => 'योगेश',
            'VINOD' => 'विनोद', 'SACHIN' => 'सचिन', 'ASHOK' => 'अशोक',
            'SWAPNIL' => 'स्वप्नील', 'VIVEK' => 'विवेक', 'PRAMOD' => 'प्रमोद',
            'AJITKUMAR' => 'अजितकुमार', 'AJIT' => 'अजित', 'PRASHANT' => 'प्रशांत',
            'SUBHASH' => 'सुभाष', 'GANESH' => 'गणेश', 'NILESH' => 'निलेश',
            'NITIN' => 'नितीन', 'AKSHAY' => 'अक्षय', 'SURESH' => 'सुरेश',
            'SHUBHAM' => 'शुभम', 'SHAHAJI' => 'शहाजी', 'MARUTI' => 'मारुती',
            'BHAUSAHEB' => 'भाऊसाहेब', 'AMOL' => 'अमोल', 'VISHWAS' => 'विश्वास',
            'RAJARAM' => 'राजाराम', 'SHARAD' => 'शरद', 'VINAYAK' => 'विनायक',
            'DNYANESHWAR' => 'ज्ञानेश्वर', 'DNYANESHWER' => 'ज्ञानेश्वर',
            'VIJAY' => 'विजय', 'SALIM' => 'सलीम', 'SAGAR' => 'सागर',
            'SANDIP' => 'संदीप', 'SANDEEP' => 'संदीप', 'AUDUNBAR' => 'औदुंबर',
            'DIPAK' => 'दीपक', 'DEEPAK' => 'दीपक', 'RAJENDRA' => 'राजेंद्र',
            'ADHIK' => 'अधिक', 'SHANKAR' => 'शंकर', 'MOHAN' => 'मोहन',
            'VIKAS' => 'विकास', 'BABULAL' => 'बाबूलाल',
            'GORAKHANATH' => 'गोरखनाथ', 'GORAKHNATH' => 'गोरखनाथ',
            'ARVIND' => 'अरविंद', 'ARUN' => 'अरुण', 'SUSHANT' => 'सुशांत',
            'ANKUSH' => 'अंकुश', 'UTTAM' => 'उत्तम', 'UDAY' => 'उदय',
            'BALAKRISHNA' => 'बालकृष्ण', 'RAMESH' => 'रमेश',
            'NANDKUMAR' => 'नंदकुमार', 'SAHEBRAO' => 'साहेबराव',
            'TUKARAM' => 'तुकाराम', 'SIDDHARAM' => 'सिद्धराम',
            'PRAKASH' => 'प्रकाश', 'NARAYAN' => 'नारायण', 'RAJESH' => 'राजेश',
            'PRADEEP' => 'प्रदीप', 'PRADIP' => 'प्रदीप', 'LIYAKAT' => 'लियाकत',
            'NAGESH' => 'नागेश', 'SHRIKANT' => 'श्रीकांत', 'LAXMAN' => 'लक्ष्मण',
            'UMESH' => 'उमेश', 'PRAVIN' => 'प्रवीण', 'MAHESH' => 'महेश',
            'RAOSAHEB' => 'रावसाहेब', 'SUHAS' => 'सुहास', 'AJAY' => 'अजय',
            'ADINATH' => 'आदिनाथ', 'ANIL' => 'अनिल', 'MAHADEV' => 'महादेव',
            'ABHIJIT' => 'अभिजीत', 'RAJU' => 'राजू', 'DHANAJI' => 'धनाजी',
            'RAVINDRA' => 'रवींद्र', 'ARJUN' => 'अर्जुन', 'HANMANT' => 'हनमंत',
            'KAKASAHEB' => 'काकासाहेब', 'RAHUL' => 'राहुल', 'SAMADHAN' => 'समाधान',
            'BHARGAVRAM' => 'भार्गवराम', 'KHANDU' => 'खंडू', 'DNYANDEV' => 'ज्ञानदेव',
            'PANDURANG' => 'पांडुरंग', 'ANANDA' => 'आनंदा', 'TANAJI' => 'तानाजी',
            'DASHRATH' => 'दशरथ', 'DASHARATH' => 'दशरथ', 'SANJAY' => 'संजय',
            'PRASAD' => 'प्रसाद',

            // Mothers
            'DHANSHREE' => 'धनश्री', 'RAGINI' => 'रागिणी', 'KOMAL' => 'कोमल',
            'SARIKA' => 'सारिका', 'ARUNA' => 'अरुणा', 'VAISHNAVI' => 'वैष्णवी',
            'PRITI' => 'प्रीती', 'SHITAL' => 'शीतल', 'REKHA' => 'रेखा',
            'PADMINI' => 'पद्मिनी', 'PUNAM' => 'पूनम', 'POONAM' => 'पूनम',
            'MANISHA' => 'मनीषा', 'AKKATAI' => 'अक्काताई', 'AAKKATAI' => 'अक्काताई',
            'NANDA' => 'नंदा', 'AMRUTANJALI' => 'अमृतांजली', 'PRIYANKA' => 'प्रियंका',
            'SUPRIYA' => 'सुप्रिया', 'PALLAVI' => 'पल्लवी', 'SNEHAL' => 'स्नेहल',
            'VANSHRI' => 'वनश्री', 'LAXMI' => 'लक्ष्मी', 'LAKSHMI' => 'लक्ष्मी',
            'SHUBHANGI' => 'शुभांगी', 'ASHWINI' => 'अश्विनी', 'ASHIWINI' => 'अश्विनी', 'ASHVINI' => 'अश्विनी',
            'KAJAL' => 'काजल', 'AAYESHA' => 'आयेशा', 'GITANJALI' => 'गीतांजली',
            'POOJA' => 'पूजा', 'PUJA' => 'पूजा', 'MADHAVI' => 'माधवी',
            'NIKITA' => 'निकिता', 'SHAMBALA' => 'शामबाला', 'SHAILA' => 'शैला',
            'RUPALI' => 'रूपाली', 'YOGITA' => 'योगिता', 'VIJAYSHREE' => 'विजयश्री',
            'REKHADEVI' => 'रेखादेवी', 'NITA' => 'नीता', 'SUSHAMA' => 'सुषमा',
            'ANUJA' => 'अनुजा', 'ROHINI' => 'रोहिणी', 'NILAM' => 'नीलम',
            'SWATI' => 'स्वाती', 'GITA' => 'गीता', 'GEETA' => 'गीता',
            'AISHWARYA' => 'ऐश्वर्या', 'BHAGYASHRI' => 'भाग्यश्री', 'ALAKA' => 'अलका',
            'GAURI' => 'गौरी', 'JAURI' => 'गौरी', 'VARSHARANI' => 'वर्षांराणी',
            'SUMAN' => 'सुमन', 'MADHURI' => 'माधुरी', 'PRATIBHA' => 'प्रतिभा',
            'VARSHA' => 'वर्षा', 'NANDINI' => 'नंदिनी', 'MANGAL' => 'मंगल',
            'KAVITA' => 'कविता', 'JYOTI' => 'ज्योती', 'VAISHALI' => 'वैशाली',
            'BIBI' => 'बीबी', 'PRADNYA' => 'प्रज्ञा', 'ARCHANA' => 'अर्चना',
            'MADHUMATI' => 'मधुमती', 'ANURADHA' => 'अनुराधा', 'SAVITA' => 'सविता',
            'ASHARANI' => 'आशाराणी', 'RESHMA' => 'रेश्मा', 'KIRAN' => 'किरण',
            'SONALI' => 'सोनाली', 'RUKSANA' => 'रुखसाना', 'NEETA' => 'नीता',
            'CHAYA' => 'छाया', 'SUVERNA' => 'सुवर्णा', 'SUVARNA' => 'सुवर्णा',
            'APARNA' => 'अपर्णा', 'SUSHILA' => 'सुशीला', 'ANITA' => 'अनिता',
            'INDRAYANI' => 'इंद्रायणी', 'RUKHMINI' => 'रुक्मिणी', 'MAHADEVI' => 'महादेवी',
            'PUSHPA' => 'पुष्पा', 'PUSPA' => 'पुष्पा',

            // Surnames (आडनावे)
            'NAGARGOJE' => 'नगरगोजे', 'CHAVAN' => 'चव्हाण', 'PATIL' => 'पाटील',
            'JADHAV' => 'जाधव', 'SATHE' => 'साठे', 'LATTHE' => 'लठ्ठे', 'LATTE' => 'लठ्ठे',
            'DHOLE' => 'ढोले', 'SARAVADE' => 'सरवडे', 'SAPKAL' => 'सपकाळ', 'SAPAKAL' => 'सपकाळ',
            'WAYADANDE' => 'वायदंडे', 'BHANDARE' => 'भंडारे', 'DHERE' => 'ढेरे',
            'MALI' => 'माळी', 'PAWAR' => 'पवार', 'SURYAWANSHI' => 'सूर्यवंशी',
            'SURYVANSHI' => 'सूर्यवंशी', 'SURYAVANSH' => 'सूर्यवंशी', 'KAMBLE' => 'कांबळे',
            'SAYYAD' => 'सय्यद', 'BHOSALE' => 'भोसले', 'BHORE' => 'भोरे',
            'GAIKWAD' => 'गायकवाड', 'GAYAKWAD' => 'गायकवाड', 'SHINDE' => 'शिंदे',
            'SUTAR' => 'सुतार', 'LOHAR' => 'लोहार', 'HANKARE' => 'हंकारे',
            'GURAV' => 'गुरव', 'FAKADE' => 'फाकडे', 'PHAKADE' => 'फाकडे',
            'PATHAN' => 'पठाण', 'KOLI' => 'कोळी', 'LAVANDAKAR' => 'लवंडकर',
            'LONDHE' => 'लोंढे', 'HAVALE' => 'हवाले', 'SHINTRE' => 'शिंत्रे',
            'NANGARE' => 'नांगरे', 'DESAI' => 'देसाई', 'HELAVI' => 'हेलवी',
            'BABAR' => 'बाबर', 'MADANE' => 'मदाने', 'MANDALE' => 'मंदाळे',
            'MANE' => 'माने', 'SAGARE' => 'सागरे'
        ];
    }

    $clean = trim(preg_replace('/\s+/', ' ', $fullName));
    if ($clean === '') return '';

    $words = explode(' ', strtoupper($clean));
    $marathiWords = [];

    foreach ($words as $w) {
        if (isset($dict[$w])) {
            $marathiWords[] = $dict[$w];
        } else {
            // High-fidelity fallback phonetic conversion
            $marathiWords[] = udise_phonetic_marathi($w);
        }
    }

    return implode(' ', $marathiWords);
}

function udise_phonetic_marathi(string $word): string
{
    $w = strtolower(trim($word));
    if ($w === '') return '';

    $rules = [
        'shree' => 'श्री', 'shri' => 'श्री', 'dnya' => 'ज्ञा',
        'ksha' => 'क्षा', 'kshi' => 'क्षी', 'kshu' => 'क्षू', 'ksh' => 'क्ष',
        'chha' => 'छा', 'chhi' => 'छी', 'chh' => 'छ',
        'bha' => 'भा', 'bhi' => 'भी', 'bhu' => 'भू', 'bhe' => 'भे', 'bho' => 'भो', 'bh' => 'भ',
        'dha' => 'धा', 'dhi' => 'धी', 'dhu' => 'धू', 'dhe' => 'धे', 'dho' => 'धो', 'dh' => 'ध',
        'gha' => 'घा', 'ghi' => 'घी', 'ghu' => 'घू', 'ghe' => 'घे', 'gho' => 'घो', 'gh' => 'घ',
        'jha' => 'झा', 'jhi' => 'झी', 'jhu' => 'झू', 'jhe' => 'झे', 'jho' => 'झो', 'jh' => 'झ',
        'kha' => 'खा', 'khi' => 'खी', 'khu' => 'खू', 'khe' => 'खे', 'kho' => 'खो', 'kh' => 'ख',
        'pha' => 'फा', 'phi' => 'फी', 'phu' => 'फू', 'phe' => 'फे', 'pho' => 'फो', 'ph' => 'फ',
        'sha' => 'शा', 'shi' => 'शी', 'shu' => 'शू', 'she' => 'शे', 'sho' => 'शो', 'sh' => 'श',
        'tha' => 'था', 'thi' => 'थी', 'thu' => 'थू', 'the' => 'थे', 'tho' => 'थो', 'th' => 'थ',
        'cha' => 'चा', 'chi' => 'ची', 'chu' => 'चू', 'che' => 'चे', 'cho' => 'चो', 'ch' => 'च',

        'ka' => 'का', 'ki' => 'की', 'ku' => 'कू', 'ke' => 'के', 'ko' => 'को', 'k' => 'क',
        'ga' => 'गा', 'gi' => 'गी', 'gu' => 'गू', 'ge' => 'गे', 'go' => 'गो', 'g' => 'ग',
        'ja' => 'जा', 'ji' => 'जी', 'ju' => 'जू', 'je' => 'जे', 'jo' => 'जो', 'j' => 'ज',
        'ta' => 'ता', 'ti' => 'ती', 'tu' => 'तू', 'te' => 'ते', 'to' => 'तो', 't' => 'त',
        'da' => 'दा', 'di' => 'दी', 'du' => 'दू', 'de' => 'दे', 'do' => 'दो', 'd' => 'द',
        'na' => 'ना', 'ni' => 'नी', 'nu' => 'नू', 'ne' => 'ने', 'no' => 'नो', 'n' => 'न',
        'pa' => 'पा', 'pi' => 'पी', 'pu' => 'पू', 'pe' => 'पे', 'po' => 'पो', 'p' => 'प',
        'ba' => 'बा', 'bi' => 'बी', 'bu' => 'बू', 'be' => 'बे', 'bo' => 'बो', 'b' => 'ब',
        'ma' => 'मा', 'mi' => 'मी', 'mu' => 'मू', 'me' => 'मे', 'mo' => 'मो', 'm' => 'म',
        'ya' => 'या', 'yi' => 'यी', 'yu' => 'यू', 'ye' => 'ये', 'yo' => 'यो', 'y' => 'य',
        'ra' => 'रा', 'ri' => 'री', 'ru' => 'रू', 're' => 'रे', 'ro' => 'रो', 'r' => 'र',
        'la' => 'ला', 'li' => 'ली', 'lu' => 'लू', 'le' => 'ले', 'lo' => 'लो', 'l' => 'ल',
        'va' => 'वा', 'vi' => 'वी', 'vu' => 'वू', 've' => 'वे', 'vo' => 'वो', 'v' => 'व',
        'wa' => 'वा', 'wi' => 'वी', 'wu' => 'वू', 'we' => 'वे', 'wo' => 'वो', 'w' => 'व',
        'sa' => 'सा', 'si' => 'सी', 'su' => 'सू', 'se' => 'से', 'so' => 'सो', 's' => 'स',
        'ha' => 'हा', 'hi' => 'ही', 'hu' => 'हू', 'he' => 'हे', 'ho' => 'हो', 'h' => 'ह',

        'aa' => 'आ', 'ee' => 'ई', 'oo' => 'ऊ', 'ai' => 'ऐ', 'au' => 'औ',
        'a' => 'अ', 'i' => 'इ', 'u' => 'उ', 'e' => 'ए', 'o' => 'ओ'
    ];

    $out = '';
    $len = strlen($w);
    $i = 0;

    while ($i < $len) {
        $matched = false;
        foreach ([5, 4, 3, 2, 1] as $matchLen) {
            if ($i + $matchLen <= $len) {
                $sub = substr($w, $i, $matchLen);
                if (isset($rules[$sub])) {
                    $out .= $rules[$sub];
                    $i += $matchLen;
                    $matched = true;
                    break;
                }
            }
        }
        if (!$matched) {
            $out .= $w[$i];
            $i++;
        }
    }

    return $out;
}

// =========================================================================
// 2. EXCEL (.XLSX) PARSER VIA ZIPARCHIVE
// =========================================================================
function udise_read_xlsx_rows(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return [];
    }

    $sharedStrings = [];
    $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($stringsXml !== false) {
        $xml = @simplexml_load_string($stringsXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $t = '';
                    foreach ($si->r as $r) $t .= (string)$r->t;
                    $sharedStrings[] = $t;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }

    if ($sheetXml === false) {
        $zip->close();
        return [];
    }

    $xml = @simplexml_load_string($sheetXml);
    $rows = [];
    if ($xml && isset($xml->sheetData->row)) {
        foreach ($xml->sheetData->row as $r) {
            $rowVals = [];
            foreach ($r->c as $c) {
                $attr = $c->attributes();
                $cellType = (string)($attr['t'] ?? '');
                $val = (string)$c->v;
                if ($cellType === 's') {
                    $rowVals[] = $sharedStrings[(int)$val] ?? '';
                } elseif ($cellType === 'inlineStr' && isset($c->is->t)) {
                    $rowVals[] = (string)$c->is->t;
                } else {
                    $rowVals[] = $val;
                }
            }
            $rows[] = $rowVals;
        }
    }
    $zip->close();
    return $rows;
}

// =========================================================================
// 3. UDISE+ CONTENT PARSER
// =========================================================================
function udise_parse_matrix(array $linesOrRows, int $school_id, PDO $db): array
{
    $headerIndex = -1;
    $headers = [];

    foreach ($linesOrRows as $i => $row) {
        if (is_string($row)) {
            $cols = str_getcsv($row);
            if (count($cols) < 2) $cols = explode("\t", $row);
        } else {
            $cols = (array)$row;
        }
        $cleanCols = array_map(fn($c) => strtolower(trim((string)$c)), $cols);
        if (in_array('class', $cleanCols, true) && (in_array('name', $cleanCols, true) || in_array('student name', $cleanCols, true))) {
            $headerIndex = $i;
            $headers = $cleanCols;
            break;
        }
    }

    if ($headerIndex === -1) {
        return ['error' => 'दिलेल्या फाईलमध्ये "Class" व "Name" स्तंभ सापडले नाहीत. कृपया UDISE+ वरील अधिकृत "List of All Students" फाईल निवडा.'];
    }

    $map = [];
    foreach ($headers as $idx => $h) {
        if ($h === 'class') $map['class'] = $idx;
        elseif ($h === 'section') $map['section'] = $idx;
        elseif ($h === 'name' || $h === 'student name') $map['name'] = $idx;
        elseif ($h === 'gender') $map['gender'] = $idx;
        elseif (str_contains($h, 'pen')) $map['pen'] = $idx;
        elseif ($h === 'father name' || str_contains($h, 'father')) $map['father'] = $idx;
        elseif ($h === 'mother name' || str_contains($h, 'mother')) $map['mother'] = $idx;
        elseif (str_contains($h, 'category') || str_contains($h, 'social')) $map['category'] = $idx;
        elseif (str_contains($h, 'minority')) $map['minority'] = $idx;
        elseif (str_contains($h, 'bpl')) $map['bpl'] = $idx;
        elseif (str_contains($h, 'cwsn') || str_contains($h, 'impairment')) $map['cwsn'] = $idx;
        elseif (str_contains($h, 'aadhaar no') || $h === 'aadhaar' || $h === 'aadhar') $map['aadhar'] = $idx;
        elseif (str_contains($h, 'apaar id') || $h === 'apaar') $map['apaar'] = $idx;
    }

    $roman_to_int = [
        'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5,
        'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10,
        '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5,
        '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10
    ];

    $section_to_mr = [
        'A' => 'अ', 'B' => 'ब', 'C' => 'क', 'D' => 'ड', 'E' => 'इ', 'F' => 'फ',
        'a' => 'अ', 'b' => 'ब', 'c' => 'क', 'd' => 'ड', 'e' => 'इ', 'f' => 'फ'
    ];

    $category_map = [
        '1-general' => 'OPEN', 'general' => 'OPEN', 'open' => 'OPEN',
        '2-sc' => 'SC', 'sc' => 'SC',
        '3-st' => 'ST', 'st' => 'ST',
        '4-obc' => 'OBC', 'obc' => 'OBC',
        '5-nt b' => 'NT-B', 'nt b' => 'NT-B', 'nt-b' => 'NT-B',
        '6-nt c' => 'NT-C', 'nt c' => 'NT-C', 'nt-c' => 'NT-C',
        '7-nt d' => 'NT-D', 'nt d' => 'NT-D', 'nt-d' => 'NT-D',
        '10-vja' => 'VJ-A', 'vja' => 'VJ-A', 'vj-a' => 'VJ-A',
        '8-sbc' => 'SBC', 'sbc' => 'SBC'
    ];

    // Fetch existing students in school to check duplicates
    $stmt_e = $db->prepare("SELECT id, std, section, name, name_mr, student_pen, roll_no FROM students WHERE school_id = ?");
    $stmt_e->execute([$school_id]);
    $existing = $stmt_e->fetchAll(PDO::FETCH_ASSOC);
    $existing_pens = [];
    $existing_names = [];
    foreach ($existing as $ex) {
        if (!empty($ex['student_pen'])) $existing_pens[trim($ex['student_pen'])] = $ex;
        $existing_names[strtolower(trim($ex['name'])) . '_' . (int)$ex['std']] = $ex;
    }

    $students = [];
    $rollCounts = [];

    for ($i = $headerIndex + 1; $i < count($linesOrRows); $i++) {
        $rawItem = $linesOrRows[$i];
        if (is_string($rawItem)) {
            $rawLine = trim($rawItem);
            if ($rawLine === '') continue;
            $row = str_getcsv($rawLine);
            if (count($row) < 3) $row = explode("\t", $rawLine);
        } else {
            $row = (array)$rawItem;
        }
        if (count($row) < 3) continue;

        $rawClass = trim((string)($row[$map['class'] ?? -1] ?? ''));
        $rawName = trim((string)($row[$map['name'] ?? -1] ?? ''));
        if ($rawName === '' || $rawClass === '') continue;

        $classClean = strtolower($rawClass);
        $std = $roman_to_int[$classClean] ?? (function_exists('std_from_grade') ? std_from_grade($rawClass) : 0);
        if ($std <= 0) $std = (int)preg_replace('/\D/', '', $classClean);
        if ($std <= 0) $std = 1;

        $rawSec = trim((string)($row[$map['section'] ?? -1] ?? 'A'));
        $section = $section_to_mr[$rawSec] ?? ($rawSec !== '' ? $rawSec : 'अ');

        $genderRaw = strtolower(trim((string)($row[$map['gender'] ?? -1] ?? '')));
        $gender = 'मुलगा';
        if (str_starts_with($genderRaw, 'f') || str_contains($genderRaw, 'मुलगी')) {
            $gender = 'मुलगी';
        }

        $pen = trim((string)($row[$map['pen'] ?? -1] ?? ''));
        $father = trim((string)($row[$map['father'] ?? -1] ?? ''));
        $mother = trim((string)($row[$map['mother'] ?? -1] ?? ''));
        $catRaw = strtolower(trim((string)($row[$map['category'] ?? -1] ?? '')));
        $category = $category_map[$catRaw] ?? ($catRaw !== '' ? strtoupper($catRaw) : 'OPEN');

        $minority = trim((string)($row[$map['minority'] ?? -1] ?? ''));
        if ($minority === 'NA' || $minority === '') $minority = 'नाही';

        $bpl = trim((string)($row[$map['bpl'] ?? -1] ?? 'No'));
        $cwsn = trim((string)($row[$map['cwsn'] ?? -1] ?? 'No'));
        $aadhar = trim((string)($row[$map['aadhar'] ?? -1] ?? ''));
        $apaar = trim((string)($row[$map['apaar'] ?? -1] ?? ''));

        $key = "{$std}-{$section}";
        $rollCounts[$key] = ($rollCounts[$key] ?? 0) + 1;
        $rollNo = $rollCounts[$key];

        // Check if student already exists
        $is_existing = false;
        $matched_id = 0;
        $existing_name_mr = '';

        if ($pen !== '' && isset($existing_pens[$pen])) {
            $is_existing = true;
            $matched_id = (int)$existing_pens[$pen]['id'];
            $existing_name_mr = $existing_pens[$pen]['name_mr'] ?? '';
            if (!empty($existing_pens[$pen]['roll_no'])) {
                $rollNo = $existing_pens[$pen]['roll_no'];
            }
        } else {
            $nameKey = strtolower($rawName) . "_{$std}";
            if (isset($existing_names[$nameKey])) {
                $is_existing = true;
                $matched_id = (int)$existing_names[$nameKey]['id'];
                $existing_name_mr = $existing_names[$nameKey]['name_mr'] ?? '';
                if (!empty($existing_names[$nameKey]['roll_no'])) {
                    $rollNo = $existing_names[$nameKey]['roll_no'];
                }
            }
        }

        // Generate Marathi transliteration
        $name_mr = $existing_name_mr ?: udise_transliterate_name($rawName);
        $father_mr = udise_transliterate_name($father);
        $mother_mr = udise_transliterate_name($mother);

        $students[] = [
            'std' => $std,
            'section' => $section,
            'roll_no' => $rollNo,
            'name' => $rawName,
            'name_mr' => $name_mr,
            'gender' => $gender,
            'father_name' => $father_mr ?: $father,
            'mother_name' => $mother_mr ?: $mother,
            'father_raw' => $father,
            'mother_raw' => $mother,
            'student_pen' => $pen,
            'caste_category' => $category,
            'minority' => $minority,
            'bpl' => $bpl,
            'handicap' => $cwsn,
            'aadhar_no' => $aadhar,
            'apaar_id' => $apaar,
            'is_existing' => $is_existing,
            'existing_id' => $matched_id
        ];
    }

    return ['students' => $students, 'total' => count($students)];
}

// =========================================================================
// 4. ACTION CONTROLLER: BATCH SAVE
// =========================================================================
$msg_success = '';
$msg_error = '';
$msg_warning = '';
$preview_students = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Step A: Parse uploaded file or pasted text
    if ($action === 'parse_data') {
        $rowsToParse = [];

        // Check file upload
        if (!empty($_FILES['udise_file']['tmp_name']) && $_FILES['udise_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['udise_file']['tmp_name'];
            $origName = strtolower($_FILES['udise_file']['name']);

            if (str_ends_with($origName, '.xlsx') || str_ends_with($origName, '.xls')) {
                $rowsToParse = udise_read_xlsx_rows($tmpPath);
                if (empty($rowsToParse)) {
                    $content = file_get_contents($tmpPath);
                    $rowsToParse = preg_split('/\r\n|\r|\n/', trim($content));
                }
            } else {
                $content = file_get_contents($tmpPath);
                $rowsToParse = preg_split('/\r\n|\r|\n/', trim($content));
            }
        } elseif (!empty($_POST['udise_text'])) {
            $rowsToParse = preg_split('/\r\n|\r|\n/', trim($_POST['udise_text']));
        } else {
            $msg_error = 'कृपया UDISE+ ची Excel (.xlsx) / CSV फाईल निवडा किंवा डेटा बॉक्समध्ये पेस्ट करा.';
        }

        if (!empty($rowsToParse)) {
            // Auto-detect school UDISE code from file header if missing in school profile
            if (empty($school['udise_code']) && !empty($rowsToParse[0])) {
                $firstRowStr = is_array($rowsToParse[0]) ? implode(' ', $rowsToParse[0]) : (string)$rowsToParse[0];
                if (preg_match('/(\d{11})/', $firstRowStr, $m_udise)) {
                    $detectedUdise = $m_udise[1];
                    try {
                        $db->prepare("UPDATE schools SET udise_code = ? WHERE id = ? AND (udise_code IS NULL OR udise_code = '')")->execute([$detectedUdise, $school_id]);
                        $_SESSION['school_udise'] = $detectedUdise;
                        $school['udise_code'] = $detectedUdise;
                    } catch (Throwable $t_u) {}
                }
            }

            $parseResult = udise_parse_matrix($rowsToParse, $school_id, $db);
            if (!empty($parseResult['error'])) {
                $msg_error = $parseResult['error'];
            } else {
                $preview_students = $parseResult['students'];
            }
        }
    }

    // Step B: Final Save to Database with strict plan quota enforcement
    elseif ($action === 'save_import') {
        $submitted = [];
        if (!empty($_POST['students_json'])) {
            $submitted = json_decode($_POST['students_json'], true) ?: [];
        } elseif (!empty($_POST['students'])) {
            $submitted = (array)$_POST['students'];
        }

        if (empty($submitted)) {
            $msg_error = 'जतन करण्यासाठी कोणतेही विद्यार्थी निवडले नाहीत.';
        } else {
            // Fresh Quota Details
            $quota = getSchoolStudentQuota($school_id);
            $max_allowed = $quota['max'];
            $current_count = $quota['count'];
            $is_unlimited = $quota['is_unlimited'];

            $inserted = 0;
            $updated = 0;
            $skipped_limit = 0;
            $std_counts = [];
            $max_imported_std = 1;

            // Prepared statements for Class & Student
            $stmt_find_class = $db->prepare("SELECT id FROM classes WHERE school_id = ? AND std = ? AND section = ?");
            $stmt_ins_class  = $db->prepare("INSERT INTO classes (school_id, std, name, section) VALUES (?, ?, ?, ?)");

            $stmt_ins_student = $db->prepare("
                INSERT INTO students (
                    school_id, class_id, std, grade, section, roll_no,
                    name, name_mr, gender, father_name, mother_name,
                    caste_category, minority, bpl, handicap,
                    student_pen, apaar_id, aadhar_no, status, is_active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, 'active', 1, NOW(), NOW()
                )
            ");

            $stmt_upd_student = $db->prepare("
                UPDATE students SET
                    name_mr = COALESCE(NULLIF(?, ''), name_mr),
                    apaar_id = COALESCE(NULLIF(?, ''), apaar_id),
                    student_pen = COALESCE(NULLIF(?, ''), student_pen),
                    aadhar_no = COALESCE(NULLIF(?, ''), aadhar_no),
                    mother_name = COALESCE(NULLIF(?, ''), mother_name),
                    father_name = COALESCE(NULLIF(?, ''), father_name),
                    caste_category = COALESCE(NULLIF(?, ''), caste_category),
                    updated_at = NOW()
                WHERE id = ? AND school_id = ?
            ");

            $db->beginTransaction();
            try {
                foreach ($submitted as $s) {
                    if (empty($s['selected'])) continue;

                    $std = (int)($s['std'] ?? 1);
                    if ($std > $max_imported_std) $max_imported_std = $std;

                    $section = trim((string)($s['section'] ?? 'अ'));
                    $roll = trim((string)($s['roll_no'] ?? ''));
                    $name = trim((string)($s['name'] ?? ''));
                    $name_mr = trim((string)($s['name_mr'] ?? ''));
                    $gender = trim((string)($s['gender'] ?? 'मुलगा'));
                    $father = trim((string)($s['father_name'] ?? ''));
                    $mother = trim((string)($s['mother_name'] ?? ''));
                    $cat = trim((string)($s['caste_category'] ?? 'OPEN'));
                    $pen = trim((string)($s['student_pen'] ?? ''));
                    $apaar = trim((string)($s['apaar_id'] ?? ''));
                    $aadhar = trim((string)($s['aadhar_no'] ?? ''));
                    $minority = trim((string)($s['minority'] ?? 'नाही'));
                    $bpl = trim((string)($s['bpl'] ?? 'No'));
                    $cwsn = trim((string)($s['handicap'] ?? 'No'));
                    $exist_id = (int)($s['existing_id'] ?? 0);

                    // Ensure class exists
                    $stmt_find_class->execute([$school_id, $std, $section]);
                    $class_id = $stmt_find_class->fetchColumn();
                    if (!$class_id) {
                        $c_name = "इयत्ता {$std} - {$section}";
                        $stmt_ins_class->execute([$school_id, $std, $c_name, $section]);
                        $class_id = (int)$db->lastInsertId();
                    }

                    if ($exist_id > 0) {
                        // Update existing record (always allowed, does not count against new student quota)
                        $stmt_upd_student->execute([
                            $name_mr, $apaar, $pen, $aadhar, $mother, $father, $cat, $exist_id, $school_id
                        ]);
                        $updated++;
                    } else {
                        // Insert new student: strictly check against plan's maximum allowed limit
                        if (!$is_unlimited && ($current_count + $inserted) >= $max_allowed) {
                            $skipped_limit++;
                            continue; // Skip this new student, do not exceed plan limit!
                        }

                        // Insert new student
                        $grade = "इयत्ता {$std}";
                        $stmt_ins_student->execute([
                            $school_id, $class_id, $std, $grade, $section, $roll,
                            $name, $name_mr, $gender, $father, $mother,
                            $cat, $minority, $bpl, $cwsn,
                            $pen, $apaar, $aadhar
                        ]);
                        $inserted++;
                    }

                    $std_counts[$std] = ($std_counts[$std] ?? 0) + 1;
                }

                // If imported standards exceed school's lower/upper boundaries, expand them
                $min_imported_std = !empty($std_counts) ? min(array_keys($std_counts)) : 1;
                $cur_lower = (int)($school['lower_class'] ?? 1);
                $cur_upper = (int)($school['upper_class'] ?? 4);
                $new_lower = min($cur_lower, $min_imported_std);
                $new_upper = max($cur_upper, $max_imported_std);
                if ($new_lower !== $cur_lower || $new_upper !== $cur_upper) {
                    $db->prepare("UPDATE schools SET lower_class = ?, upper_class = ? WHERE id = ?")->execute([$new_lower, $new_upper, $school_id]);
                }

                $db->commit();
                
                // Synchronize both classes and students into school's tenant SQLite database
                if (function_exists('sync_classes_to_sqlite')) {
                    sync_classes_to_sqlite($school_id);
                }
                if (function_exists('sync_students_to_sqlite')) {
                    sync_students_to_sqlite($school_id);
                }

                $breakdown = [];
                ksort($std_counts);
                foreach ($std_counts as $st => $c) {
                    $breakdown[] = "<a href='" . APP_URL . "/modules/classes/index.php?tab=students&std={$st}' class='badge bg-white text-primary border text-decoration-none px-2.5 py-1.5 shadow-xs'>इ. {$st} ली: {$c} विद्यार्थी <i class='bi bi-arrow-right-short'></i></a>";
                }
                $first_std_imported = array_key_first($std_counts) ?: 1;
                $msg_success = "अभिनंदन! एकूण <b>" . ($inserted + $updated) . "</b> विद्यार्थ्यांची माहिती यशस्वीरीत्या जतन झाली! (नवीन: {$inserted}, अद्ययावत: {$updated})<br><div class='mt-2 d-flex flex-wrap align-items-center gap-1.5'><b>वर्ग पहा:</b> " . implode(' ', $breakdown) . "</div>";

                if ($skipped_limit > 0) {
                    $msg_warning = "⚠️ <b>विद्यार्थी मर्यादा सूचना:</b> आपल्या योजनेची कमाल मर्यादा (<b>{$max_allowed} विद्यार्थी</b>) गाठली गेल्यामुळे <b>{$skipped_limit} नवीन विद्यार्थी वगळण्यात आले आहेत</b>. उर्वरित सर्व विद्यार्थी एकाच वेळी जोडण्यासाठी कृपया <a href='" . APP_URL . "/subscription/plans.php' class='btn btn-sm btn-warning text-dark fw-bold ms-2 rounded-pill px-3 shadow-xs'>योजना अपग्रेड करा</a>.";
                }
            } catch (Exception $e) {
                $db->rollBack();
                $msg_error = "डेटा सेव्ह करताना त्रुटी: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars($school['name_mr'] ?: $school['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --app-primary: #2563eb;
            --app-primary-dark: #1d4ed8;
            --app-surface: #ffffff;
            --app-bg: #f8fafc;
            --app-border: #e2e8f0;
        }
        body {
            background-color: var(--app-bg);
            font-family: 'Poppins', 'Noto Sans Devanagari', sans-serif;
            color: #1e293b;
            padding-bottom: calc(90px + env(safe-area-inset-bottom, 0px));
        }
        .baloo { font-family: 'Baloo 2', 'Noto Sans Devanagari', sans-serif; }

        /* Top Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #0d9488 100%);
            color: #ffffff;
            border-radius: 18px;
            padding: 20px 24px;
            box-shadow: 0 10px 25px -5px rgba(30, 58, 138, 0.25);
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .hero-banner {
                padding: 16px 18px;
                border-radius: 14px;
            }
        }

        /* Dropzone */
        .upload-dropzone {
            border: 2px dashed #93c5fd;
            background: #eff6ff;
            border-radius: 16px;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .upload-dropzone:hover {
            border-color: #3b82f6;
            background: #dbeafe;
        }

        /* Status Badges */
        .badge-new {
            background-color: #dcfce7;
            color: #15803d;
            font-weight: 700;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .badge-exist {
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 700;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        /* Class Filter Horizontal Scroll Container */
        .class-filter-scroll {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 6px;
            scrollbar-width: thin;
        }
        .class-filter-scroll::-webkit-scrollbar {
            height: 4px;
        }
        .class-filter-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* Inputs & Typography */
        .name-mr-input {
            font-weight: 700;
            color: #0f172a;
            background-color: #fffdf5;
            border: 1.5px solid #fde68a;
            border-radius: 8px;
            font-size: 15px;
        }
        .name-mr-input:focus {
            background-color: #ffffff;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        /* Prevent auto-zoom on mobile inputs */
        @media (max-width: 768px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }

        /* Sticky Bottom Save Bar */
        .sticky-save-bar {
            position: fixed;
            bottom: calc(65px + env(safe-area-inset-bottom, 0px));
            left: 0;
            right: 0;
            z-index: 1035;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -6px 25px rgba(15, 23, 42, 0.12);
            padding: 10px 16px;
        }
        @media (min-width: 992px) {
            .sticky-save-bar {
                position: sticky;
                bottom: 20px;
                border-radius: 16px;
                border: 1px solid #e2e8f0;
                margin-top: 20px;
                margin-bottom: 20px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            }
        }

        /* =====================================================================
           CARD VIEW vs TABLE VIEW SWITCHING SYSTEM
           ===================================================================== */
        
        /* 1. TABLE VIEW (Desktop / Wide screens) */
        .view-table .table-responsive-custom {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid var(--app-border);
            border-radius: 12px;
            background: #ffffff;
        }
        .view-table table thead {
            position: sticky;
            top: 0;
            background: #f1f5f9;
            z-index: 10;
            border-bottom: 2px solid #cbd5e1;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .view-table .mobile-only-block {
            display: none !important;
        }

        /* 2. CARD VIEW (Mobile / Tablet Touch Cards) */
        .view-cards .table-responsive-custom {
            overflow: visible !important;
            max-height: none !important;
            border: none !important;
            background: transparent !important;
        }
        .view-cards table thead {
            display: none !important;
        }
        .view-cards table, 
        .view-cards table tbody {
            display: block !important;
            width: 100% !important;
        }
        .view-cards tr.student-row {
            display: block !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 16px !important;
            margin-bottom: 14px !important;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05) !important;
            position: relative !important;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .view-cards tr.student-row:focus-within {
            border-color: #3b82f6 !important;
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.12) !important;
        }
        .view-cards td {
            display: block !important;
            padding: 0 !important;
            border: none !important;
            background: transparent !important;
        }
        .view-cards .table-only-block {
            display: none !important;
        }
        .view-cards .mobile-only-block {
            display: block !important;
        }
    </style>
</head>
<body class="view-cards" id="pageBody">

<div class="container-fluid px-2 px-sm-3 px-lg-5 py-3 py-lg-4" style="max-width: 1400px; margin: 0 auto;">

    <!-- Top Navigation Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php" class="text-decoration-none">मुख्यपृष्ठ</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/classes/index.php" class="text-decoration-none">वर्ग व्यवस्थापन</a></li>
                <li class="breadcrumb-item active fw-bold text-primary" aria-current="page">UDISE+ आयात</li>
            </ol>
        </nav>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/modules/classes/index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> वर्ग व्यवस्थापन
            </a>
            <a href="<?= APP_URL ?>/students/list.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="bi bi-people me-1"></i> विद्यार्थी यादी
            </a>
        </div>
    </div>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-warning text-dark fw-bold px-2.5 py-1 rounded-pill">
                        ✨ स्वयंचलित मराठी युनिकोड नावे
                    </span>
                    <span class="badge bg-white/20 text-white rounded-pill">
                        UDISE: <?= htmlspecialchars($school['udise_code'] ?? '—') ?>
                    </span>
                    <span class="badge bg-success text-white rounded-pill">
                        इ. १ ते <?= htmlspecialchars((string)($school['upper_class'] ?? 8)) ?> सर्व वर्ग सपोर्ट
                    </span>
                </div>
                <h3 class="baloo fw-extrabold mb-1" style="font-size: 22px;">
                    UDISE+ SDMS वरून सर्व विद्यार्थ्यांची थेट आयात
                </h3>
                <p class="mb-0 text-white-50 small" style="font-size: 13px; line-height: 1.5;">
                    UDISE+ पोर्टलवरील <b>"List of All Students"</b> Excel (.xlsx) फाईल अपलोड करा. इंग्रजी नावांचे <b>मराठी युनिकोडमध्ये रूपांतर</b> आपोआप होईल आणि मोबाईलवर थेट संपादन करता येईल.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end d-none d-md-block">
                <div class="bg-white/10 p-2.5 rounded-3 text-start d-inline-block border border-white/20">
                    <div class="small text-white fw-bold mb-1"><i class="bi bi-shield-check text-warning me-1"></i> स्मार्ट वैशिष्ट्ये:</div>
                    <ul class="mb-0 text-white-50 ps-3" style="font-size: 11.5px; line-height: 1.6;">
                        <li>सर्व वर्गांचे (इ. १ ते ८) विद्यार्थी एका क्लिकवर सेव्ह</li>
                        <li>मोबाईल टच-फ्रेंडली कार्ड व्ह्यू व सुलभ संपादन</li>
                        <li>Student PEN, APAAR ID व आधार सुरक्षित नोंद</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg_success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 p-3 mb-4 border-0" role="alert">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-check-circle-fill fs-3 text-success mt-1"></i>
                <div class="flex-grow-1">
                    <h5 class="alert-heading fw-bold mb-1 baloo text-success">यशस्वीरीत्या पूर्ण झाले!</h5>
                    <div><?= $msg_success ?></div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if (!empty($first_std_imported)): ?>
                        <a href="<?= APP_URL ?>/modules/classes/index.php?tab=students&std=<?= (int)$first_std_imported ?>" class="btn btn-success btn-sm fw-bold rounded-pill px-3 shadow-xs">
                            <i class="bi bi-people-fill me-1"></i> इ. <?= (int)$first_std_imported ?> चे विद्यार्थी पहा
                        </a>
                    <?php else: ?>
                        <a href="<?= APP_URL ?>/modules/classes/index.php" class="btn btn-success btn-sm fw-bold rounded-pill px-3">
                            <i class="bi bi-building-check me-1"></i> वर्ग व्यवस्थापन
                        </a>
                    <?php endif; ?>
                    <a href="<?= APP_URL ?>/students/list.php" class="btn btn-outline-success btn-sm fw-bold bg-white rounded-pill px-3">
                        <i class="bi bi-people me-1"></i> सर्व विद्यार्थी
                    </a>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($msg_warning): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm rounded-4 p-3 mb-4 border-warning" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                <div><?= $msg_warning ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($msg_error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4 p-3 mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">त्रुटी आढळली!</h6>
                    <div><?= htmlspecialchars($msg_error) ?></div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($preview_students)): ?>
    <!-- ===================================================================== -->
    <!-- STEP 1: UPLOAD EXCEL / CSV OR PASTE TEXT                             -->
    <!-- ===================================================================== -->
    <div class="row g-4 justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-3 px-sm-4 border-bottom">
                    <ul class="nav nav-pills card-header-pills" id="importTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold small px-3 px-sm-4 py-2" id="file-tab" data-bs-toggle="tab" data-bs-target="#filePane" type="button" role="tab">
                                <i class="bi bi-file-earmark-excel-fill text-success me-1"></i> UDISE+ Excel / CSV फाईल
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold small px-3 px-sm-4 py-2" id="paste-tab" data-bs-toggle="tab" data-bs-target="#pastePane" type="button" role="tab">
                                <i class="bi bi-clipboard2-data-fill text-primary me-1"></i> थेट मजकूर पेस्ट करा
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-3 p-sm-4 p-lg-5">
                    <form method="POST" enctype="multipart/form-data" action="">
                        <input type="hidden" name="action" value="parse_data">

                        <div class="tab-content" id="importTabContent">
                            <!-- File Upload Pane -->
                            <div class="tab-pane fade show active" id="filePane" role="tabpanel">
                                <label for="udiseFile" class="upload-dropzone d-block mb-3" style="cursor: pointer;">
                                    <i class="bi bi-cloud-arrow-up-fill fs-1 text-primary mb-2 d-block"></i>
                                    <h5 class="fw-bold mb-1 baloo">UDISE+ फाईल निवडा किंवा येथे स्पर्श करा</h5>
                                    <p class="text-muted small mb-3">
                                        समर्थित: <b>.xlsx, .xls, .csv</b> (UDISE+ SDMS वरील सर्व वर्गांची एक्सेल)
                                    </p>
                                    <input type="file" id="udiseFile" name="udise_file" accept=".xlsx,.xls,.csv,.txt" class="d-none" onchange="updateFileName(this)">
                                    <span class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                        <i class="bi bi-folder2-open me-1"></i> फाईल सिलेक्ट करा
                                    </span>
                                    <div id="selectedFileName" class="mt-3 fw-bold text-success" style="display:none;"></div>
                                </label>
                            </div>

                            <!-- Direct Paste Pane -->
                            <div class="tab-pane fade" id="pastePane" role="tabpanel">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">
                                        UDISE+ एक्सेल मधील कॉपी केलेला मजकूर येथे पेस्ट करा:
                                    </label>
                                    <textarea name="udise_text" class="form-control font-monospace small" rows="9" placeholder="उदा. Class,Section,Name,Gender,Student PEN..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-light p-3 rounded-3 border mb-4">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0"></i>
                                <span class="small text-muted">
                                    <b>टिप:</b> फाईलमधील इ. १ ते ८ मधील सर्व वर्गांचे विद्यार्थी आपोआप वर्गीकृत होतात आणि मराठी युनिकोड नावे जनरेट केली जातात.
                                </span>
                            </div>
                        </div>

                        <div class="text-center">
                            <button type="submit" class="btn btn-success btn-lg rounded-pill px-4 px-sm-5 fw-bold shadow w-100 w-sm-auto">
                                <i class="bi bi-lightning-charge-fill me-1"></i> माहिती तपासा व मराठी रूपांतर करा
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ===================================================================== -->
    <!-- STEP 2: PREVIEW & INLINE EDITING (CARDS + TABLE MODES)                -->
    <!-- ===================================================================== -->
    <?php
    $new_count_in_file = 0;
    $exist_count_in_file = 0;
    foreach ($preview_students as $ps) {
        if (!empty($ps['is_existing'])) $exist_count_in_file++;
        else $new_count_in_file++;
    }
    $is_quota_exceeded = (!$student_quota['is_unlimited'] && $new_count_in_file > $student_quota['remaining']);
    ?>

    <?php if ($is_quota_exceeded): ?>
        <div class="alert alert-warning border-warning shadow-sm rounded-4 p-3 mb-3">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div>
                    <h6 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> योजना विद्यार्थी संख्या मर्यादा सूचना
                    </h6>
                    <div class="small text-muted" style="line-height: 1.5;">
                        तुमच्या <b><?= htmlspecialchars($student_quota['plan_name']) ?></b> नुसार कमाल मर्यादा <b><?= $student_quota['max'] ?> विद्यार्थी</b> आहे.<br>
                        सध्या शाळेत <b><?= $student_quota['count'] ?></b> विद्यार्थी नोंदणीकृत असून, नवीन विद्यार्थ्यांसाठी केवळ <b><?= $student_quota['remaining'] ?> जागा शिल्लक</b> आहेत.<br>
                        या फाईलमध्ये <b><?= $new_count_in_file ?> नवीन विद्यार्थी</b> (व <?= $exist_count_in_file ?> अद्ययावत) आहेत. योजनेनुसार फक्त पहिले <b><?= $student_quota['remaining'] ?> नवीन विद्यार्थी</b> आपोआप निवडले आहेत. उर्वरित सर्व विद्यार्थी एकाच वेळी जोडण्यासाठी कृपया योजना अपग्रेड करा.
                    </div>
                </div>
                <a href="<?= APP_URL ?>/subscription/plans.php" target="_blank" class="btn btn-warning text-dark fw-bold rounded-pill px-4 shadow-xs flex-shrink-0">
                    <i class="bi bi-arrow-up-circle-fill me-1"></i> योजना अपग्रेड करा
                </a>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="udiseSaveForm" novalidate onsubmit="return prepareUdiseSubmit(event)">
        <input type="hidden" name="action" value="save_import">
        <input type="hidden" name="students_json" id="studentsJsonPayload">

        <!-- Controls & Filter Header -->
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-3 p-lg-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="badge bg-primary rounded-pill px-3 py-1.5 fs-6 baloo">
                                एकूण विद्यार्थी: <?= count($preview_students) ?>
                            </span>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-1.5 fs-6">
                                <i class="bi bi-mortarboard-fill me-1"></i> <?= htmlspecialchars($student_quota['plan_name']) ?>: <?= $student_quota['count'] ?>/<?= $student_quota['is_unlimited'] ? '∞' : $student_quota['max'] ?> (शिल्लक: <?= $student_quota['is_unlimited'] ? 'अमर्यादित' : $student_quota['remaining'] ?>)
                            </span>
                            <?php
                            $allocated_new_calc = 0;
                            $initial_checked_count = 0;
                            foreach ($preview_students as $st_calc) {
                                $is_ex_calc = !empty($st_calc['is_existing']);
                                $is_over_calc = false;
                                if (!$is_ex_calc && !$student_quota['is_unlimited']) {
                                    $allocated_new_calc++;
                                    if ($allocated_new_calc > $student_quota['remaining']) {
                                        $is_over_calc = true;
                                    }
                                }
                                if (!$is_over_calc) {
                                    $initial_checked_count++;
                                }
                            }
                            ?>
                            <span class="text-muted small">
                                (निवडलेले: <span id="selectedCounter" class="fw-bold text-dark"><?= $initial_checked_count ?></span>)
                            </span>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-pencil-square text-warning me-1"></i>
                            <b>मराठी नावे तपासा:</b> बदल असल्यास बॉक्सवर स्पर्श करून मराठी नाव सहज संपादित करू शकता.
                        </p>
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        <!-- View Switcher -->
                        <div class="btn-group shadow-sm rounded-pill me-1" role="group">
                            <button type="button" class="btn btn-sm btn-primary px-3 rounded-start-pill view-btn" id="btnViewCards" onclick="setViewMode('cards')">
                                <i class="bi bi-grid-fill me-1"></i> कार्ड्स
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary px-3 rounded-end-pill view-btn" id="btnViewTable" onclick="setViewMode('table')">
                                <i class="bi bi-table me-1"></i> तक्ता
                            </button>
                        </div>

                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="toggleAllCheckboxes(true)">
                            <i class="bi bi-check-all me-1"></i> सर्व
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="toggleAllCheckboxes(false)">
                            <i class="bi bi-x me-1"></i> रद्द
                        </button>
                    </div>
                </div>

                <!-- Class Filter & Search Bar -->
                <div class="row g-2 align-items-center mt-3 pt-3 border-top">
                    <div class="col-12 col-md-8">
                        <div class="class-filter-scroll">
                            <span class="small fw-bold text-muted me-1 flex-shrink-0"><i class="bi bi-funnel me-1"></i> वर्ग:</span>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 class-filter-btn flex-shrink-0" data-std="all" onclick="filterByClass('all', this)">
                                सर्व वर्ग (<?= count($preview_students) ?>)
                            </button>
                            <?php
                            $found_stds = array_unique(array_column($preview_students, 'std'));
                            sort($found_stds);
                            foreach ($found_stds as $fs):
                                $cCount = count(array_filter($preview_students, fn($x) => $x['std'] === $fs));
                            ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 class-filter-btn flex-shrink-0" data-std="<?= $fs ?>" onclick="filterByClass(<?= $fs ?>, this)">
                                    इ. <?= $fs ?> ली (<?= $cCount ?>)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="tableSearch" class="form-control border-start-0 rounded-end-pill" placeholder="नाव किंवा PEN शोधा..." onkeyup="searchTable()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Items (Structured for both Cards & Table views) -->
        <div class="table-responsive-custom shadow-sm mb-4">
            <table class="table table-hover table-striped align-middle mb-0" id="studentsTable">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 45px;">
                            <input type="checkbox" id="selectAllHeader" <?= $initial_checked_count === count($preview_students) ? 'checked' : '' ?> onchange="toggleAllCheckboxes(this.checked)">
                        </th>
                        <th style="width: 50px;">अनु.</th>
                        <th style="width: 110px;">वर्ग व तुकडी</th>
                        <th style="width: 75px;">रोल नं.</th>
                        <th style="width: 250px;">इंग्रजी नाव (UDISE+)</th>
                        <th style="min-width: 260px;" class="text-primary">मराठी नाव (संपादनयोग्य <i class="bi bi-pencil-fill small"></i>)</th>
                        <th style="width: 100px;">लिंग</th>
                        <th style="width: 140px;">आईचे नाव</th>
                        <th style="width: 140px;">वडिलांचे नाव</th>
                        <th style="width: 90px;">प्रवर्ग</th>
                        <th style="width: 120px;">Student PEN</th>
                        <th style="width: 95px;" class="text-center">स्थिती</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $allocated_new = 0;
                    foreach ($preview_students as $idx => $s): 
                        $is_ex = !empty($s['is_existing']);
                        $is_over_limit = false;
                        if (!$is_ex && !$student_quota['is_unlimited']) {
                            $allocated_new++;
                            if ($allocated_new > $student_quota['remaining']) {
                                $is_over_limit = true;
                            }
                        }
                        $is_checked = !$is_over_limit;
                    ?>
                        <tr class="student-row<?= $is_over_limit ? ' opacity-75' : '' ?>" data-std="<?= $s['std'] ?>" data-idx="<?= $idx ?>">
                            <!-- Col 1: Checkbox & Hidden Metadata -->
                            <td class="text-center">
                                <div class="mobile-only-block mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="checkbox" class="student-check form-check-input mt-0" data-field="selected" <?= $is_checked ? 'checked' : '' ?> style="width: 22px; height: 22px; cursor: pointer;" onchange="syncRowCheckbox(this)">
                                            <span class="badge bg-secondary-subtle text-secondary fw-bold px-2 py-1">#<?= $idx + 1 ?></span>
                                            <span class="badge bg-primary text-white px-2 py-1">
                                                इ. <?= $s['std'] ?> - <?= htmlspecialchars($s['section']) ?>
                                            </span>
                                            <span class="badge bg-light text-dark border px-2 py-1">
                                                रोल: <?= htmlspecialchars($s['roll_no']) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <?php if ($s['is_existing']): ?>
                                                <span class="badge-exist"><i class="bi bi-arrow-repeat"></i> अद्ययावत</span>
                                            <?php elseif ($is_over_limit): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="प्लॅनची विद्यार्थी मर्यादा संपली आहे"><i class="bi bi-lock-fill"></i> मर्यादा संपली</span>
                                            <?php else: ?>
                                                <span class="badge-new"><i class="bi bi-plus-circle"></i> नवीन</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-only-block">
                                    <input type="checkbox" class="student-check form-check-input" data-field="selected" <?= $is_checked ? 'checked' : '' ?> onchange="syncRowCheckbox(this)">
                                </div>

                                <input type="hidden" data-field="existing_id" value="<?= $s['existing_id'] ?>">
                                <input type="hidden" data-field="apaar_id" value="<?= htmlspecialchars($s['apaar_id']) ?>">
                                <input type="hidden" data-field="aadhar_no" value="<?= htmlspecialchars($s['aadhar_no']) ?>">
                                <input type="hidden" data-field="minority" value="<?= htmlspecialchars($s['minority']) ?>">
                                <input type="hidden" data-field="bpl" value="<?= htmlspecialchars($s['bpl']) ?>">
                                <input type="hidden" data-field="handicap" value="<?= htmlspecialchars($s['handicap']) ?>">
                            </td>

                            <!-- Col 2: Index (Table only) -->
                            <td class="small text-muted text-center table-only-block"><?= $idx + 1 ?></td>

                            <!-- Col 3: Class & Section -->
                            <td class="table-only-block">
                                <div class="d-flex gap-1">
                                    <select data-field="std" class="form-select form-select-sm py-0 px-1 fw-bold" style="width: 60px;" onchange="syncRowStd(this)">
                                        <?php for ($c = 1; $c <= 8; $c++): ?>
                                            <option value="<?= $c ?>" <?= $s['std'] === $c ? 'selected' : '' ?>>इ.<?= $c ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <input type="text" data-field="section" value="<?= htmlspecialchars($s['section']) ?>" class="form-control form-control-sm text-center py-0 px-1 fw-bold" style="width: 35px;">
                                </div>
                            </td>

                            <!-- Col 4: Roll No -->
                            <td class="table-only-block">
                                <input type="text" data-field="roll_no" value="<?= htmlspecialchars($s['roll_no']) ?>" class="form-control form-control-sm text-center py-1 px-1">
                            </td>

                            <!-- Col 5: English Name -->
                            <td class="table-only-block">
                                <input type="text" data-field="name" value="<?= htmlspecialchars($s['name']) ?>" class="form-control form-control-sm text-slate-700 bg-light" readonly>
                            </td>

                            <!-- Col 6: Unicode Marathi Name (Primary Editable Input) -->
                            <td>
                                <div class="mobile-only-block">
                                    <div class="d-flex align-items-center justify-content-between mb-1 mt-1">
                                        <label class="form-label fw-bold text-dark mb-0 small">
                                            <i class="bi bi-pencil-fill text-warning me-1"></i> मराठी नाव (युनिकोड - तपासा / बदला):
                                        </label>
                                        <span class="badge bg-warning-subtle text-warning-emphasis" style="font-size: 10px;">मराठी संपादन</span>
                                    </div>
                                    <input type="text" data-field="name_mr" value="<?= htmlspecialchars($s['name_mr']) ?>" class="form-control name-mr-input py-2 px-3 fw-bold" placeholder="मराठी नाव लिहा...">
                                    <div class="small text-muted mt-1 mb-2 ps-1">
                                        <i class="bi bi-translate text-primary me-1"></i> इंग्रजी UDISE नाव: <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars($s['name']) ?></span>
                                    </div>
                                </div>

                                <div class="table-only-block">
                                    <input type="text" data-field="name_mr" value="<?= htmlspecialchars($s['name_mr']) ?>" class="form-control form-control-sm name-mr-input py-1">
                                </div>
                            </td>

                            <!-- Col 7: Gender -->
                            <td class="table-only-block">
                                <select data-field="gender" class="form-select form-select-sm py-1">
                                    <option value="मुलगा" <?= $s['gender'] === 'मुलगा' ? 'selected' : '' ?>>मुलगा</option>
                                    <option value="मुलगी" <?= $s['gender'] === 'मुलगी' ? 'selected' : '' ?>>मुलगी</option>
                                    <option value="इतर" <?= $s['gender'] === 'इतर' ? 'selected' : '' ?>>इतर</option>
                                </select>
                            </td>

                            <!-- Col 8: Mother Name -->
                            <td class="table-only-block">
                                <input type="text" data-field="mother_name" value="<?= htmlspecialchars($s['mother_name']) ?>" class="form-control form-control-sm py-1">
                            </td>

                            <!-- Col 9: Father Name -->
                            <td class="table-only-block">
                                <input type="text" data-field="father_name" value="<?= htmlspecialchars($s['father_name']) ?>" class="form-control form-control-sm py-1">
                            </td>

                            <!-- Col 10: Category -->
                            <td class="table-only-block">
                                <select data-field="caste_category" class="form-select form-select-sm py-1">
                                    <?php foreach (['OPEN', 'SC', 'ST', 'OBC', 'VJ-A', 'NT-B', 'NT-C', 'NT-D', 'SBC'] as $cat): ?>
                                        <option value="<?= $cat ?>" <?= $s['caste_category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <!-- Col 11: Student PEN -->
                            <td class="table-only-block">
                                <input type="text" data-field="student_pen" value="<?= htmlspecialchars($s['student_pen']) ?>" class="form-control form-control-sm py-1 font-monospace small bg-light" readonly>
                            </td>

                            <!-- Col 12: Status (Table only) -->
                            <td class="text-center table-only-block">
                                <?php if ($s['is_existing']): ?>
                                    <span class="badge-exist"><i class="bi bi-arrow-repeat me-0.5"></i> अद्ययावत</span>
                                <?php elseif ($is_over_limit): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="प्लॅनची विद्यार्थी मर्यादा संपली आहे"><i class="bi bi-lock-fill me-0.5"></i> मर्यादा संपली</span>
                                <?php else: ?>
                                    <span class="badge-new"><i class="bi bi-plus-circle me-0.5"></i> नवीन</span>
                                <?php endif; ?>
                            </td>

                            <!-- Mobile Card Details Grid -->
                            <td class="mobile-only-block pt-1">
                                <div class="bg-light p-2.5 rounded-3 border">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label text-muted mb-0" style="font-size: 11px;">वर्ग व तुकडी:</label>
                                            <div class="d-flex gap-1">
                                                <select data-field="std" class="form-select form-select-sm py-1 px-1 fw-bold" onchange="syncRowStd(this)">
                                                    <?php for ($c = 1; $c <= 8; $c++): ?>
                                                        <option value="<?= $c ?>" <?= $s['std'] === $c ? 'selected' : '' ?>>इ.<?= $c ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <input type="text" data-field="section" value="<?= htmlspecialchars($s['section']) ?>" class="form-control form-control-sm text-center py-1 px-1 fw-bold" style="width: 45px;">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted mb-0" style="font-size: 11px;">रोल नंबर:</label>
                                            <input type="text" data-field="roll_no" value="<?= htmlspecialchars($s['roll_no']) ?>" class="form-control form-control-sm py-1">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted mb-0" style="font-size: 11px;">लिंग:</label>
                                            <select data-field="gender" class="form-select form-select-sm py-1">
                                                <option value="मुलगा" <?= $s['gender'] === 'मुलगा' ? 'selected' : '' ?>>मुलगा</option>
                                                <option value="मुलगी" <?= $s['gender'] === 'मुलगी' ? 'selected' : '' ?>>मुलगी</option>
                                                <option value="इतर" <?= $s['gender'] === 'इतर' ? 'selected' : '' ?>>इतर</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted mb-0" style="font-size: 11px;">जात प्रवर्ग:</label>
                                            <select data-field="caste_category" class="form-select form-select-sm py-1">
                                                <?php foreach (['OPEN', 'SC', 'ST', 'OBC', 'VJ-A', 'NT-B', 'NT-C', 'NT-D', 'SBC'] as $cat): ?>
                                                    <option value="<?= $cat ?>" <?= $s['caste_category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted mb-0" style="font-size: 11px;">आईचे नाव:</label>
                                            <input type="text" data-field="mother_name" value="<?= htmlspecialchars($s['mother_name']) ?>" class="form-control form-control-sm py-1">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label text-muted mb-0" style="font-size: 11px;">वडिलांचे नाव:</label>
                                            <input type="text" data-field="father_name" value="<?= htmlspecialchars($s['father_name']) ?>" class="form-control form-control-sm py-1">
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center font-monospace text-muted mt-1" style="font-size: 11px;">
                                                <span>PEN: <b><?= htmlspecialchars($s['student_pen'] ?: '—') ?></b></span>
                                                <input type="hidden" data-field="student_pen" value="<?= htmlspecialchars($s['student_pen']) ?>">
                                                <input type="hidden" data-field="name" value="<?= htmlspecialchars($s['name']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Sticky Bottom Save Bar (Docked gracefully on mobile above bottom nav) -->
        <div class="sticky-save-bar">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="max-width: 1400px; margin: 0 auto;">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success rounded-pill px-3 py-2 fs-6 baloo">
                        <span id="barSelectedCounter"><?= $initial_checked_count ?></span> विद्यार्थी निवडले
                    </span>
                    <a href="<?= APP_URL ?>/modules/classes/import_udise.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-none d-sm-inline-block">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> नवीन फाईल
                    </a>
                </div>
                <button type="submit" id="btnSaveAllStudents" class="btn btn-success fw-bold rounded-pill px-4 px-sm-5 py-2 shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-down-fill fs-5"></i>
                    <span>सिस्टीममध्ये जतन करा (Save All)</span>
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/mobile_bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// =========================================================================
// VIEW MODE SWITCHER (CARDS vs TABLE)
// =========================================================================
function setViewMode(mode) {
    var body = document.getElementById('pageBody');
    var btnCards = document.getElementById('btnViewCards');
    var btnTable = document.getElementById('btnViewTable');

    if (mode === 'table') {
        body.classList.remove('view-cards');
        body.classList.add('view-table');
        if (btnCards) { btnCards.classList.remove('btn-primary'); btnCards.classList.add('btn-outline-primary'); }
        if (btnTable) { btnTable.classList.remove('btn-outline-primary'); btnTable.classList.add('btn-primary'); }
    } else {
        body.classList.remove('view-table');
        body.classList.add('view-cards');
        if (btnCards) { btnCards.classList.remove('btn-outline-primary'); btnCards.classList.add('btn-primary'); }
        if (btnTable) { btnTable.classList.remove('btn-primary'); btnTable.classList.add('btn-outline-primary'); }
    }

    try {
        localStorage.setItem('udise_import_view_pref', mode);
    } catch(e) {}
}

document.addEventListener('DOMContentLoaded', function() {
    var isMobile = window.innerWidth < 768;
    var saved = null;
    try { saved = localStorage.getItem('udise_import_view_pref'); } catch(e) {}
    var defaultMode = saved ? saved : (isMobile ? 'cards' : 'table');
    setViewMode(defaultMode);
});

// Sync std changes across card and table inputs
function syncRowStd(selectEl) {
    var row = selectEl.closest('.student-row');
    if (!row) return;
    var std = selectEl.value;
    row.setAttribute('data-std', std);
    var stdSelects = row.querySelectorAll('[data-field="std"]');
    stdSelects.forEach(function(s) { s.value = std; });
}

// Sync row checkbox between card view and table view
function syncRowCheckbox(input) {
    var row = input.closest('.student-row');
    if (!row) return;
    var isChecked = input.checked;
    var checks = row.querySelectorAll('.student-check');
    checks.forEach(function(c) { c.checked = isChecked; });
    updateSelectedCount();
}

// Update file name display
function updateFileName(input) {
    if (input.files && input.files[0]) {
        var fn = input.files[0].name;
        var el = document.getElementById('selectedFileName');
        if (el) {
            el.style.display = 'block';
            el.innerHTML = '<i class="bi bi-file-earmark-check-fill me-1 text-success"></i> निवडलेली फाईल: ' + fn;
        }
    }
}

// Toggle checkboxes
function toggleAllCheckboxes(checked) {
    var rows = document.querySelectorAll('.student-row');
    rows.forEach(function(row) {
        if (row.style.display !== 'none') {
            var checks = row.querySelectorAll('.student-check');
            checks.forEach(function(c) { c.checked = checked; });
        }
    });
    var header = document.getElementById('selectAllHeader');
    if (header) header.checked = checked;
    updateSelectedCount();
}

// Update counters
function updateSelectedCount() {
    var rows = document.querySelectorAll('.student-row');
    var count = 0;
    rows.forEach(function(row) {
        if (row.style.display !== 'none') {
            var c = row.querySelector('.student-check');
            if (c && c.checked) count++;
        }
    });

    var c1 = document.getElementById('selectedCounter');
    var c2 = document.getElementById('barSelectedCounter');
    if (c1) c1.innerText = count;
    if (c2) c2.innerText = count;
}

// Class Filter
function filterByClass(std, btn) {
    document.querySelectorAll('.class-filter-btn').forEach(function(b) {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline-secondary');
    });
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-primary');

    var rows = document.querySelectorAll('.student-row');
    rows.forEach(function(row) {
        if (std === 'all' || row.getAttribute('data-std') == std) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    updateSelectedCount();
}

// Search Filter
function searchTable() {
    var q = document.getElementById('tableSearch').value.toLowerCase();
    var rows = document.querySelectorAll('.student-row');
    rows.forEach(function(row) {
        var text = row.innerText.toLowerCase();
        var inputs = row.querySelectorAll('input, select');
        var inputValues = '';
        inputs.forEach(function(inp) { inputValues += ' ' + inp.value.toLowerCase(); });

        if (text.indexOf(q) > -1 || inputValues.indexOf(q) > -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    updateSelectedCount();
}

// =========================================================================
// BULLETPROOF FORM SUBMISSION VIA JSON (Prevents max_input_vars truncation)
// =========================================================================
function prepareUdiseSubmit(e) {
    var rows = document.querySelectorAll('.student-row');
    var data = [];

    rows.forEach(function(row) {
        var check = row.querySelector('.student-check');
        if (!check || !check.checked) return;

        var getVal = function(field) {
            var els = row.querySelectorAll('[data-field="' + field + '"]');
            if (!els || els.length === 0) return '';
            // If multiple elements (one in mobile card, one in table), prefer visible or non-empty edited
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                if (el.offsetParent !== null && el.value.trim() !== '') {
                    return el.value.trim();
                }
            }
            for (var i = 0; i < els.length; i++) {
                if (els[i].value.trim() !== '') {
                    return els[i].value.trim();
                }
            }
            return els[0].value.trim();
        };

        var nameMr = getVal('name_mr');
        var nameEng = getVal('name');
        if (!nameMr && nameEng) {
            nameMr = nameEng;
        }

        data.push({
            selected: 1,
            existing_id: getVal('existing_id'),
            apaar_id: getVal('apaar_id'),
            aadhar_no: getVal('aadhar_no'),
            minority: getVal('minority'),
            bpl: getVal('bpl'),
            handicap: getVal('handicap'),
            std: getVal('std'),
            section: getVal('section') || 'A',
            roll_no: getVal('roll_no'),
            name: nameEng,
            name_mr: nameMr,
            gender: getVal('gender') || 'इतर',
            mother_name: getVal('mother_name'),
            father_name: getVal('father_name'),
            caste_category: getVal('caste_category') || 'OPEN',
            student_pen: getVal('student_pen')
        });
    });

    if (data.length === 0) {
        alert('कृपया जतन करण्यासाठी किमान एक विद्यार्थी निवडा.');
        if (e && e.preventDefault) e.preventDefault();
        return false;
    }

    // Pack into a single JSON payload field
    var payloadEl = document.getElementById('studentsJsonPayload');
    if (payloadEl) {
        payloadEl.value = JSON.stringify(data);
    }

    // Show loading spinner on submit button
    var submitBtn = document.getElementById('btnSaveAllStudents');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>विद्यार्थी डेटा जतन होत आहे... कृपया थांबा';
    }

    return true;
}
</script>

</body>
</html>
