import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../core/api.dart';
import '../core/config.dart';
import '../core/connectivity.dart';
import '../core/local_db.dart';
import '../core/session.dart';
import '../theme.dart';
import 'cce.dart';
import 'exam_paper.dart';
import 'holidays.dart';
import 'hpc.dart';
import 'mdm.dart';
import 'paripath.dart';
import 'school.dart';
import 'students.dart';
import 'timetable.dart';
import 'login.dart';
import 'widgets.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final w = MediaQuery.sizeOf(context).width;
    final cols = w >= 900 ? 4 : (w >= 600 ? 3 : 2);
    final mods = <(IconData, String, Color, Widget)>[
      (Icons.groups_outlined, 'विद्यार्थी', const Color(0xFF0D9488), const StudentsScreen()),
      (Icons.assignment_turned_in_outlined, 'सातत्यपूर्ण मूल्यमापन', const Color(0xFFB83B5E), const CceScreen()),
      (Icons.insert_chart_outlined, 'समग्र प्रगती पत्रक', const Color(0xFF0F8A84), const HpcScreen()),
      (Icons.schedule_outlined, 'वेळापत्रक', const Color(0xFF7C3AED), const TimetableScreen()),
      (Icons.description_outlined, 'प्रश्नपत्रिका', const Color(0xFF2563A8), const ExamPaperScreen()),
      (Icons.wb_sunny_outlined, 'परिपाठ', const Color(0xFFB7791F), const ParipathScreen()),
      (Icons.restaurant_outlined, 'पोषण आहार', const Color(0xFFD97706), const MdmScreen()),
      (Icons.calendar_month_outlined, 'दिनदर्शिका व सुट्ट्या', TG.g600, const HolidaysScreen()),
      (Icons.verified_outlined, 'बोनाफाईड दाखला', const Color(0xFF4B5563), const BonafideScreen()),
      (Icons.badge_outlined, 'शिक्षक संपादन', const Color(0xFF0F766E), const SchoolListScreen(teachers: true)),
      (Icons.class_outlined, 'वर्ग', const Color(0xFF0369A1), const SchoolListScreen(teachers: false)),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('अधिक')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        ListenableBuilder(listenable: NetStatus.I, builder: (_, __) => NetStatus.I.isOnline ? const SizedBox.shrink() : Container(
          margin: const EdgeInsets.only(bottom: 12), padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(color: Colors.orange.shade100, borderRadius: BorderRadius.circular(10)),
          child: const Row(children: [Icon(Icons.wifi_off, color: Colors.orange, size: 20), SizedBox(width: 8), Expanded(child: Text('ऑफलाइन मोड', style: TextStyle(color: Colors.deepOrange, fontSize: 13)))]),
        )),
        GridView.count(crossAxisCount: cols, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.35,
          children: [
            for (final m in mods)
              IconTile(icon: m.$1, label: m.$2, color: m.$3, sub: 'ॲपमध्ये', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => m.$4))),
          ],
        ),
        const SectionTitle('खाते'),
        Card(child: Column(children: [
          ListTile(leading: const Icon(Icons.workspace_premium_outlined, color: TG.amber), title: const Text('सदस्यता योजना'),
              subtitle: Text('${Session.I.school['plan_name'] ?? 'मोफत'}'), trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const PlansScreen()))),
          const Divider(height: 1),
          ListTile(leading: const Icon(Icons.school_outlined, color: TG.g600), title: const Text('शाळा माहिती'), trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SchoolProfileScreen()))),
          const Divider(height: 1),
          ListTile(leading: const Icon(Icons.privacy_tip_outlined), title: const Text('गोपनीयता धोरण'), onTap: () => openWeb(kPrivacyPath)),
          const Divider(height: 1),
          ListTile(leading: const Icon(Icons.logout), title: const Text('लॉगआउट'), onTap: () => _logout(context, false)),
          const Divider(height: 1),
          ListTile(leading: const Icon(Icons.person_remove_outlined, color: Colors.red),
              title: const Text('खाते व डेटा हटवा', style: TextStyle(color: Colors.red)), onTap: () => _delete(context)),
        ])),
        const SizedBox(height: 16),
        FutureBuilder<PackageInfo>(
          future: PackageInfo.fromPlatform(),
          builder: (_, s) => Center(child: Text('TechGuruji v${s.data?.version ?? '1.0.0'}', style: const TextStyle(color: TG.muted))),
        ),
      ]),
    );
  }

  Future<void> _logout(BuildContext context, bool skipApi) async {
    final pending = await LocalDb.I.pendingCount();
    if (!context.mounted) return;
    if (!skipApi && pending > 0) {
      final go = await showDialog<bool>(context: context, builder: (c) => AlertDialog(
        title: const Text('Sync बाकी आहे'),
        content: Text('$pending बदल अजून पोर्टलवर पाठवले नाहीत. लॉगआउट केल्यावर ते हरवतील.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('मागे जा')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('तरी लॉगआउट करा')),
        ],
      ));
      if (go != true) return;
    }
    if (!skipApi) {
      try {
        await Api.post('account.php', {'action': 'logout'});
      } on ApiException catch (_) {}
    }
    await LocalDb.I.wipe();
    await Session.I.clear();
    if (!context.mounted) return;
    Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
  }

  Future<void> _delete(BuildContext context) async {
    final pass = TextEditingController();
    final ok = await showDialog<bool>(context: context, builder: (c) => AlertDialog(
          title: const Text('खाते हटवायचे?'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            const Text('लॉगिन लगेच बंद होईल आणि शाळेचा सर्व डेटा ३० दिवसांत कायमचा हटवला जाईल.'),
            const SizedBox(height: 12),
            TextField(controller: pass, obscureText: true, decoration: const InputDecoration(labelText: 'पासवर्ड')),
          ]),
          actions: [TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('रद्द')),
            FilledButton(style: FilledButton.styleFrom(backgroundColor: Colors.red), onPressed: () => Navigator.pop(c, true), child: const Text('हटवा'))],
        ));
    if (ok != true || !context.mounted) return;
    try {
      final r = await Api.post('account.php', {'action': 'delete', 'password': pass.text});
      if (!context.mounted) return;
      toast(context, '${r['message']}');
      await _logout(context, true);
    } on ApiException catch (e) {
      if (context.mounted) toast(context, e.message, error: true);
    }
  }
}

class PlansScreen extends StatefulWidget {
  const PlansScreen({super.key});
  @override
  State<PlansScreen> createState() => _PlansScreenState();
}

class _PlansScreenState extends State<PlansScreen> {
  final _rzp = Razorpay();
  List _plans = [];
  Map<String, dynamic>? _sub;
  bool _loading = true, _paying = false;
  String? _err;

  @override
  void initState() {
    super.initState();
    _rzp.on(Razorpay.EVENT_PAYMENT_SUCCESS, _onSuccess);
    _rzp.on(Razorpay.EVENT_PAYMENT_ERROR, _onError);
    _rzp.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});
    _load();
  }

  @override
  void dispose() {
    _rzp.clear();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final p = await Api.get('app_payment.php', {'action': 'plans'});
      final s = await Api.get('app_payment.php', {'action': 'status'});
      await LocalDb.I.putCache('plans', p);
      setState(() {
        _plans = p['plans'] as List;
        _sub = Map<String, dynamic>.from(s['subscription'] as Map);
        _err = null;
      });
    } on ApiException catch (e) {
      final c = await LocalDb.I.getCache('plans');
      setState(() {
        _plans = (c?['plans'] as List?) ?? [];
        _err = e.message;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _buy(Map p) async {
    setState(() => _paying = true);
    try {
      final o = await Api.post('app_payment.php', {'action': 'create_order', 'plan_id': p['id']});
      _rzp.open({
        'key': o['key_id'],
        'order_id': o['order_id'],
        'amount': o['amount'],
        'currency': o['currency'],
        'name': o['name'],
        'description': o['description'],
        'prefill': o['prefill'],
        'theme': {'color': '#167A5B'},
      });
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
      setState(() => _paying = false);
    }
  }

  Future<void> _onSuccess(PaymentSuccessResponse r) async {
    try {
      final v = await Api.post('app_payment.php', {
        'action': 'verify',
        'razorpay_order_id': r.orderId,
        'razorpay_payment_id': r.paymentId,
        'razorpay_signature': r.signature,
      });
      final sub = Map<String, dynamic>.from(v['subscription'] as Map);
      await Session.I.updateSchool({'plan_name': sub['plan_name'], 'is_subscribed': sub['is_subscribed']});
      if (mounted) toast(context, '${v['message']}');
      await _load();
    } on ApiException catch (e) {
      if (mounted) toast(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _paying = false);
    }
  }

  void _onError(PaymentFailureResponse r) {
    setState(() => _paying = false);
    toast(context, 'पेमेंट रद्द / अयशस्वी', error: true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('सदस्यता योजना')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(padding: const EdgeInsets.all(16), children: [
              if (_err != null) Card(color: const Color(0xFFFDF2DF), child: ListTile(leading: const Icon(Icons.wifi_off), title: Text(_err!))),
              if (_sub != null)
                Card(
                  color: TG.g50,
                  child: ListTile(
                    leading: const Icon(Icons.workspace_premium, color: TG.g600),
                    title: Text('${_sub!['plan_name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                    subtitle: Text(_sub!['is_subscribed'] == true ? 'सक्रिय — ${_sub!['subscription_end']} पर्यंत' : 'मोफत योजना'),
                  ),
                ),
              const SizedBox(height: 8),
              for (final p in _plans.where((x) => (x['price'] as num) > 0))
                Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Expanded(child: Text('${(p['name_mr'] ?? '').toString().isNotEmpty ? p['name_mr'] : p['name']}',
                            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800))),
                        Text('₹${(p['price'] as num).toStringAsFixed(0)}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: TG.g800)),
                      ]),
                      Text('${p['duration_months']} महिने', style: const TextStyle(color: TG.muted)),
                      const SizedBox(height: 8),
                      for (final f in (p['features'] as List).take(5))
                        Padding(padding: const EdgeInsets.only(bottom: 4), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          const Icon(Icons.check, size: 18, color: TG.g600), const SizedBox(width: 6), Expanded(child: Text('$f')),
                        ])),
                      const SizedBox(height: 8),
                      SizedBox(width: double.infinity, child: FilledButton(onPressed: _paying ? null : () => _buy(p as Map), child: const Text('Razorpay ने खरेदी करा'))),
                    ]),
                  ),
                ),
              const Text('UPI, कार्ड किंवा नेट बँकिंगने सुरक्षित पेमेंट. पेमेंटची पडताळणी सर्व्हरवर होते.',
                  textAlign: TextAlign.center, style: TextStyle(color: TG.muted, fontSize: 13)),
            ]),
    );
  }
}



/// School profile editing screen
class SchoolProfileScreen extends StatefulWidget {
  const SchoolProfileScreen({super.key});
  @override
  State<SchoolProfileScreen> createState() => _SchoolProfileScreenState();
}

class _SchoolProfileScreenState extends State<SchoolProfileScreen> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _nameMrCtrl;
  late final TextEditingController _phoneCtrl;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: '${Session.I.school['name'] ?? ''}');
    _nameMrCtrl = TextEditingController(text: '${Session.I.school['name_mr'] ?? ''}');
    _phoneCtrl = TextEditingController(text: '${Session.I.school['phone'] ?? ''}');
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _nameMrCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      // Save locally first so dashboard reflects changes immediately
      await Session.I.updateSchool({
        'name': _nameCtrl.text.trim(),
        'name_mr': _nameMrCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
      });
      if (mounted) toast(context, 'माहिती फोनवर जतन झाली. पोर्टलवर अपडेट करण्यासाठी खालील बटण दाबा.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = Session.I.school;
    return Scaffold(
      appBar: AppBar(title: const Text('शाळा माहिती')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(gradient: const LinearGradient(colors: [TG.g900, TG.g600]), borderRadius: BorderRadius.circular(20)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.asset('assets/images/logo.png', width: 56, height: 56)),
            const SizedBox(height: 12),
            Text('${s['name_mr'] ?? s['name'] ?? 'शाळा'}', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)),
            Text('UDISE: ${s['udise_code'] ?? '-'}', style: const TextStyle(color: Color(0xFFCFE7DC))),
            if ('${s['district'] ?? ''}'.isNotEmpty) Text('${s['district']}', style: const TextStyle(color: Color(0xFFBFE3D3))),
          ]),
        ),
        const SizedBox(height: 20),
        TextField(controller: _nameCtrl, decoration: const InputDecoration(labelText: 'शाळेचे नाव (English)', prefixIcon: Icon(Icons.school_outlined))),
        const SizedBox(height: 14),
        TextField(controller: _nameMrCtrl, decoration: const InputDecoration(labelText: 'शाळेचे नाव (मराठी)', prefixIcon: Icon(Icons.school_outlined))),
        const SizedBox(height: 14),
        TextField(controller: _phoneCtrl, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'संपर्क क्रमांक', prefixIcon: Icon(Icons.phone_outlined))),
        const SizedBox(height: 20),
        FilledButton.icon(onPressed: _busy ? null : _save,
          icon: _busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.save_outlined),
          label: const Text('फोनवर जतन करा')),
        const SizedBox(height: 10),
        OutlinedButton.icon(
          icon: const Icon(Icons.open_in_browser),
          label: const Text('पोर्टलवर संपूर्ण माहिती अपडेट करा'),
          onPressed: () => openWeb('/account/profile.php'),
        ),
        const SizedBox(height: 16),
        Card(child: Column(children: [
          ListTile(leading: const Icon(Icons.badge_outlined), title: Text('ईमेल: ${s['email'] ?? '-'}'), dense: true),
          const Divider(height: 1),
          ListTile(leading: const Icon(Icons.credit_card_outlined), title: Text('सदस्यता: ${s['plan_name'] ?? 'मोफत'}'), dense: true),
          if ('${s['subscription_end'] ?? ''}'.isNotEmpty) ...[
            const Divider(height: 1),
            ListTile(leading: const Icon(Icons.event_outlined), title: Text('सदस्यता संपते: ${s['subscription_end']}'), dense: true),
          ],
        ])),
      ]),
    );
  }
}
