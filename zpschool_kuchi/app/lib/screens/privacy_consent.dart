import 'package:flutter/material.dart';

import '../core/config.dart';
import '../core/session.dart';
import '../theme.dart';
import 'login.dart';
import 'widgets.dart';

class PrivacyConsentScreen extends StatelessWidget {
  const PrivacyConsentScreen({super.key});

  @override
  Widget build(BuildContext context) {
    const points = [
      ['शाळेचा डेटा', 'विद्यार्थी, हजेरी, गुण व टाचण माहिती फक्त तुमच्या शाळेच्या खात्यासाठी वापरली जाते.'],
      ['फोनवर साठवण', 'ऑफलाइन कामासाठी डेटा फक्त या फोनमधील ॲपच्या खासगी जागेत ठेवला जातो.'],
      ['Sync तुमच्या हातात', 'तुम्ही Sync दाबल्यावरच डेटा सर्व्हरवर पाठवला जातो — सुरक्षित HTTPS वरून.'],
      ['परवानग्या', 'फक्त इंटरनेट. संपर्क, लोकेशन, SMS, कॅमेरा किंवा मायक्रोफोन वापरले जात नाहीत.'],
      ['पेमेंट', 'पेमेंट Razorpay द्वारे होते. कार्ड/UPI माहिती ॲप साठवत नाही.'],
      ['खाते हटवा', 'सेटिंग्जमधून कधीही खाते व डेटा हटवण्याची विनंती करता येते.'],
    ];
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          Expanded(
            child: ListView(padding: const EdgeInsets.all(24), children: [
              Image.asset('assets/images/logo.png', width: 64, height: 64, alignment: Alignment.centerLeft),
              const SizedBox(height: 16),
              const Text('तुमची गोपनीयता', style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: TG.ink)),
              const Text('ॲप वापरण्यापूर्वी कृपया वाचा.', style: TextStyle(color: TG.muted, fontSize: 16)),
              const SizedBox(height: 16),
              for (final p in points)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Icon(Icons.check_circle, color: TG.g600, size: 22),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(p[0], style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                        Text(p[1], style: const TextStyle(color: TG.muted, height: 1.4)),
                      ]),
                    ),
                  ]),
                ),
              TextButton.icon(
                onPressed: () => openWeb(kPrivacyPath),
                icon: const Icon(Icons.open_in_new),
                label: const Text('संपूर्ण गोपनीयता धोरण वाचा'),
              ),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 20),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () {
                  Session.I.privacyAccepted = true;
                  Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
                },
                child: const Text('मी सहमत आहे, पुढे चला'),
              ),
            ),
          ),
        ]),
      ),
    );
  }
}
