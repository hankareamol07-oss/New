import 'package:flutter/material.dart';

import '../core/remote.dart';
import 'common.dart';
import 'widgets.dart';

class FeeScreen extends StatefulWidget {
  const FeeScreen({super.key});
  @override
  State<FeeScreen> createState() => _FeeScreenState();
}

class _FeeScreenState extends State<FeeScreen> {
  int std = 0;

  @override
  Widget build(BuildContext context) => DefaultTabController(
        length: 2,
        child: Scaffold(
          appBar: AppBar(
            title: const Text('शुल्क व्यवस्थापन'),
            bottom: const TabBar(tabs: [Tab(text: 'विद्यार्थी शुल्क'), Tab(text: 'अहवाल / PDF')]),
          ),
          body: TabBarView(children: [_feeList(), _reports()]),
        ),
      );

  Widget _feeList() => ApiLoader(
        path: 'fee.php',
        query: {'grade': '$std'},
        builder: (c, d, reload) {
          final students = (d['students'] as List? ?? []).cast<Map>();
          final summary = (d['summary'] as Map?) ?? {};
          final totalDue = (summary['total_expected'] as num?)?.toDouble() ?? 0;
          final totalPaid = (summary['total_collected'] as num?)?.toDouble() ?? 0;
          // Extract available grades from students for a grade filter chip
          final grades = {for (final s in students) '${s['grade'] ?? ''}'}..remove('');
          if (std == 0 && grades.isNotEmpty) {
            std = int.tryParse(grades.first) ?? 0;
          }
          final stds = [(0, 'सर्व'), for (final g in (grades.toList()..sort((a, b) => (int.tryParse(a) ?? 0).compareTo(int.tryParse(b) ?? 0)))) (int.tryParse(g) ?? 0, 'इ. $g')];
          final filtered = std == 0 ? students : students.where((s) => '${s['grade']}' == '$std').toList();
          return Column(children: [
            Padding(padding: const EdgeInsets.fromLTRB(12, 12, 12, 0), child: Row(children: [
              Expanded(child: Pick<int>(label: 'इयत्ता', value: std, items: stds, onChanged: (v) => setState(() => std = v ?? std))),
            ])),
            if (totalDue > 0) _summaryRow(totalDue, totalPaid),
            Expanded(child: filtered.isEmpty
                ? const EmptyState('शुल्क माहिती नाही.', icon: Icons.payments_outlined)
                : ListView.separated(padding: const EdgeInsets.all(12), itemCount: filtered.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (ctx, i) => _studentTile(ctx, filtered[i], reload))),
          ]);
        },
      );

  Widget _summaryRow(double totalDue, double totalPaid) => Container(
    margin: const EdgeInsets.fromLTRB(12, 10, 12, 0), padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: const Color(0xFFEFF8F4), borderRadius: BorderRadius.circular(10)),
    child: Row(children: [
      _amt('एकूण मागणी', totalDue, const Color(0xFF0B3D2E)),
      Container(width: 1, height: 40, color: const Color(0xFFE3EAE6)),
      _amt('भरले', totalPaid, const Color(0xFF167A5B)),
      Container(width: 1, height: 40, color: const Color(0xFFE3EAE6)),
      _amt('बाकी', totalDue - totalPaid, const Color(0xFFB3261E)),
    ]),
  );

  Widget _amt(String l, double v, Color c) => Expanded(child: Column(children: [
    Text(l, style: const TextStyle(color: Color(0xFF687A73), fontSize: 12)),
    Text('₹${v.toStringAsFixed(0)}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: c)),
  ]));

  Widget _studentTile(BuildContext ctx, Map s, VoidCallback reload) {
    final due = (s['total_fee'] as num?)?.toDouble() ?? 0;
    final paid = (s['paid_amount'] as num?)?.toDouble() ?? 0;
    final payStatus = s['status'] ?? s['payment_status'] ?? 'unpaid';
    final sColor = payStatus == 'paid' ? Colors.green : payStatus == 'partial' ? Colors.orange : Colors.red;
    final sText = payStatus == 'paid' ? 'पूर्ण भरले' : payStatus == 'partial' ? 'आंशिक' : 'बाकी';
    return ListTile(
      title: Text('${s['name_mr'] ?? s['name']}', style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text('हजेरी ${s['roll_no'] ?? '-'} • देणे ₹${(due - paid).toStringAsFixed(0)}'),
      trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
        Text(sText, style: TextStyle(color: sColor, fontWeight: FontWeight.w700, fontSize: 13)),
        Text('₹${paid.toStringAsFixed(0)} / ₹${due.toStringAsFixed(0)}', style: const TextStyle(color: Color(0xFF687A73), fontSize: 12)),
      ]),
      onTap: () => _collectFee(ctx, s, reload),
    );
  }

  Widget _reports() => ListView(padding: const EdgeInsets.all(12), children: const [
    ReportTile(title: 'शुल्क संकलन अहवाल', page: 'modules/fee/report.php', params: {}),
  ]);


  Future<void> _collectFee(BuildContext ctx, Map student, VoidCallback reload) async {
    final amtCtrl = TextEditingController();
    final remarkCtrl = TextEditingController();
    String mode = 'cash';
    final ok = await showModalBottomSheet<bool>(
      context: ctx, isScrollControlled: true,
      builder: (_) => StatefulBuilder(builder: (c, setS) => Padding(
        padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(c).viewInsets.bottom + 16),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('शुल्क जमा: ${student['name_mr'] ?? student['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
          const SizedBox(height: 12),
          TextField(controller: amtCtrl, keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'रक्कम (₹)', border: OutlineInputBorder(), prefixText: '₹ ')),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(value: mode,
            decoration: const InputDecoration(labelText: 'पेमेंट पद्धत', border: OutlineInputBorder()),
            items: const [
              DropdownMenuItem(value: 'cash', child: Text('रोख')),
              DropdownMenuItem(value: 'upi', child: Text('UPI')),
              DropdownMenuItem(value: 'cheque', child: Text('चेक')),
            ],
            onChanged: (v) => setS(() => mode = v ?? 'cash')),
          const SizedBox(height: 10),
          TextField(controller: remarkCtrl, decoration: const InputDecoration(labelText: 'शेरा (ऐच्छिक)', border: OutlineInputBorder())),
          const SizedBox(height: 12),
          SizedBox(width: double.infinity, child: FilledButton.icon(
            icon: const Icon(Icons.payment), label: const Text('शुल्क जमा करा'),
            onPressed: () => Navigator.pop(c, true))),
        ]),
      )),
    );
    if (ok != true || amtCtrl.text.trim().isEmpty) return;
    try {
      final online = await Remote.save('fee.php', {
        'action': 'collect', 'student_id': student['id'],
        'amount': double.tryParse(amtCtrl.text.trim()) ?? 0,
        'mode': mode == 'cash' ? 'रोख (Cash)' : mode == 'upi' ? 'UPI' : 'चेक',
        'remarks': remarkCtrl.text.trim(),
        'paid_on': DateTime.now().toIso8601String().substring(0, 10),
      });
      if (ctx.mounted) toast(ctx, online ? 'शुल्क जमा झाले' : 'फोनवर जतन झाले. Sync केल्यावर पोर्टलवर जाईल.');
      reload();
    } catch (e) {
      if (ctx.mounted) toast(ctx, '$e', error: true);
    }
  }
}
