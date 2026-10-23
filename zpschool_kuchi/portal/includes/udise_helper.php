<?php
/**
 * UDISE+ SDMS Data Parser & Transliteration Engine
 * Shared between modules/classes/import_udise.php and api/import_udise.php
 */

if (!function_exists('udise_transliterate_name')) {
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
                $marathiWords[] = udise_phonetic_marathi($w);
            }
        }

        return implode(' ', $marathiWords);
    }
}

if (!function_exists('udise_phonetic_marathi')) {
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
}

if (!function_exists('udise_read_xlsx_rows')) {
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
}

if (!function_exists('udise_parse_matrix')) {
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
        $existing_pens = [];
        $existing_names = [];
        try {
            $stmt_e = $db->prepare("SELECT id, std, section, name, name_mr, student_pen, roll_no FROM students WHERE school_id = ?");
            $stmt_e->execute([$school_id]);
            $existing = $stmt_e->fetchAll(PDO::FETCH_ASSOC);
            foreach ($existing as $ex) {
                if (!empty($ex['student_pen'])) $existing_pens[trim($ex['student_pen'])] = $ex;
                $existing_names[strtolower(trim($ex['name'])) . '_' . (int)$ex['std']] = $ex;
            }
        } catch (Throwable $t) {}

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

            if (!empty($pen) && isset($existing_pens[$pen])) {
                $is_existing = true;
                $matched_id = (int)$existing_pens[$pen]['id'];
                $existing_name_mr = (string)$existing_pens[$pen]['name_mr'];
            } else {
                $nKey = strtolower($rawName) . '_' . $std;
                if (isset($existing_names[$nKey])) {
                    $is_existing = true;
                    $matched_id = (int)$existing_names[$nKey]['id'];
                    $existing_name_mr = (string)$existing_names[$nKey]['name_mr'];
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
}

if (!function_exists('udise_save_students')) {
    function udise_save_students(array $submitted, int $school_id, PDO $db): array
    {
        if (empty($submitted)) {
            return ['error' => 'जतन करण्यासाठी कोणतेही विद्यार्थी निवडले नाहीत.'];
        }

        $quota = function_exists('getSchoolStudentQuota') ? getSchoolStudentQuota($school_id) : ['is_unlimited' => true, 'max' => 999999, 'count' => 0];
        $max_allowed = $quota['max'];
        $current_count = $quota['count'];
        $is_unlimited = $quota['is_unlimited'];

        $inserted = 0;
        $updated = 0;
        $skipped_limit = 0;

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
                // If selected flag is present, require it to be true
                if (isset($s['selected']) && empty($s['selected'])) continue;

                $std = (int)($s['std'] ?? 1);
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
                    $stmt_upd_student->execute([
                        $name_mr, $apaar, $pen, $aadhar, $mother, $father, $cat, $exist_id, $school_id
                    ]);
                    $updated++;
                } else {
                    if (!$is_unlimited && ($current_count + $inserted) >= $max_allowed) {
                        $skipped_limit++;
                        continue;
                    }

                    $grade = "इयत्ता {$std}";
                    $stmt_ins_student->execute([
                        $school_id, $class_id, $std, $grade, $section, $roll,
                        $name, $name_mr, $gender, $father, $mother,
                        $cat, $minority, $bpl, $cwsn,
                        $pen, $apaar, $aadhar
                    ]);
                    $inserted++;
                }
            }
            $db->commit();

            // Dual DB Sync: Sync MySQL student rows to SQLite tenant DB
            if (function_exists('sync_students_to_sqlite')) {
                try {
                    sync_students_to_sqlite($school_id);
                } catch (Throwable $t_sync) {}
            }

            return [
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped_limit' => $skipped_limit,
                'total' => $inserted + $updated
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['error' => 'डेटा सेव्ह करताना त्रुटी: ' . $e->getMessage()];
        }
    }
}
