import 'package:flutter/material.dart';

import '../core/remote.dart';

/// Loads a JSON API (online-first, offline copy) and rebuilds when [reload] is called.
class ApiLoader extends StatefulWidget {
  const ApiLoader({super.key, required this.path, required this.query, required this.builder});
  final String path;
  final Map<String, String> query;
  final Widget Function(BuildContext, Map<String, dynamic>, VoidCallback reload) builder;

  @override
  State<ApiLoader> createState() => _ApiLoaderState();
}

class _ApiLoaderState extends State<ApiLoader> {
  late Future<Map<String, dynamic>> _f;

  @override
  void initState() {
    super.initState();
    _f = Remote.get(widget.path, widget.query);
  }

  @override
  void didUpdateWidget(covariant ApiLoader old) {
    super.didUpdateWidget(old);
    if (old.path != widget.path || old.query.toString() != widget.query.toString()) _reload();
  }

  void _reload() => setState(() => _f = Remote.get(widget.path, widget.query));

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
        future: _f,
        builder: (c, s) {
          if (s.connectionState != ConnectionState.done) {
            return const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()));
          }
          if (s.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.cloud_off, size: 48, color: Colors.grey),
                  const SizedBox(height: 12),
                  Text('${s.error}', textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  FilledButton.icon(onPressed: _reload, icon: const Icon(Icons.refresh), label: const Text('पुन्हा प्रयत्न करा')),
                ]),
              ),
            );
          }
          final d = s.data!;
          return Column(children: [
            if (d['_offline'] == true)
              Container(
                width: double.infinity,
                color: Colors.amber.shade100,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: const Text('ऑफलाइन: शेवटची जतन केलेली माहिती दिसत आहे', style: TextStyle(fontSize: 13)),
              ),
            Expanded(child: widget.builder(c, d, _reload)),
          ]);
        },
      );
}

/// Labeled dropdown that never crashes on a value missing from the list.
class Pick<T> extends StatelessWidget {
  const Pick({super.key, required this.label, required this.value, required this.items, required this.onChanged, this.dense = false});
  final String label;
  final T? value;
  final List<(T, String)> items;
  final ValueChanged<T?> onChanged;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final has = items.any((e) => e.$1 == value);
    return DropdownButtonFormField<T>(
      value: has ? value : null,
      isExpanded: true,
      isDense: dense,
      decoration: InputDecoration(labelText: label, border: const OutlineInputBorder(), isDense: true),
      items: [for (final e in items) DropdownMenuItem(value: e.$1, child: Text(e.$2, overflow: TextOverflow.ellipsis))],
      onChanged: onChanged,
    );
  }
}

/// A tile that renders a portal print page into a PDF (print / save / share).
class ReportTile extends StatelessWidget {
  const ReportTile({super.key, required this.title, required this.page, required this.params, this.subtitle});
  final String title;
  final String? subtitle;
  final String page;
  final Map<String, dynamic> params;

  @override
  Widget build(BuildContext context) => Card(
        child: ListTile(
          leading: const Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFB3261E)),
          title: Text(title),
          subtitle: subtitle == null ? null : Text(subtitle!),
          trailing: const Icon(Icons.print_outlined),
          onTap: () => Remote.printReport(context, page, params, name: title),
        ),
      );
}

String stdLabel(int std) => const {
      1: 'पहिली', 2: 'दुसरी', 3: 'तिसरी', 4: 'चौथी', 5: 'पाचवी', 6: 'सहावी', 7: 'सातवी', 8: 'आठवी', 9: 'नववी', 10: 'दहावी'
    }[std] ??
    'इयत्ता $std';

int asInt(dynamic v, [int d = 0]) => v is int ? v : int.tryParse('${v ?? ''}') ?? d;

String studentLabel(Map s) {
  final n = '${s['name_mr'] ?? ''}'.trim().isNotEmpty ? s['name_mr'] : s['name'];
  final r = '${s['roll_no'] ?? ''}'.trim();
  return r.isEmpty ? '$n' : '$r. $n';
}

/// Portal-style select that shows every option in full (long Marathi text) in a bottom sheet.
class SheetPick extends StatelessWidget {
  const SheetPick({super.key, required this.label, required this.options, required this.onPicked, this.value = '', this.hint = '-- निवडा --', this.icon});
  final String label, value, hint;
  final List<String> options;
  final ValueChanged<String> onPicked;
  final IconData? icon;

  Future<void> _open(BuildContext context) async {
    final r = await showModalBottomSheet<String>(context: context, isScrollControlled: true, showDragHandle: true,
      builder: (c) => DraggableScrollableSheet(expand: false, initialChildSize: .6, maxChildSize: .92, builder: (c, sc) => Column(children: [
        Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 8), child: Text(label, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15))),
        const Divider(height: 1),
        Expanded(child: ListView.separated(controller: sc, itemCount: options.length, separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (_, i) => ListTile(
            leading: CircleAvatar(radius: 13, backgroundColor: options[i] == value ? const Color(0xFF059669) : const Color(0xFFE5E7EB),
              child: Text('${i + 1}', style: TextStyle(fontSize: 11, color: options[i] == value ? Colors.white : Colors.black87))),
            title: Text(options[i], style: const TextStyle(fontSize: 14, height: 1.4)),
            trailing: options[i] == value ? const Icon(Icons.check_circle, color: Color(0xFF059669)) : null,
            onTap: () => Navigator.pop(c, options[i])))),
      ])));
    if (r != null) onPicked(r);
  }

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: options.isEmpty ? null : () => _open(context),
    borderRadius: BorderRadius.circular(6),
    child: InputDecorator(
      decoration: InputDecoration(labelText: label, prefixIcon: icon == null ? null : Icon(icon, size: 20), border: const OutlineInputBorder(), isDense: true,
        suffixIcon: const Icon(Icons.arrow_drop_down_circle_outlined), enabled: options.isNotEmpty),
      child: Text(value.isNotEmpty ? value : (options.isEmpty ? 'पर्याय उपलब्ध नाहीत' : '$hint (${options.length})'),
        maxLines: 3, overflow: TextOverflow.ellipsis,
        style: TextStyle(fontSize: 13.5, color: value.isNotEmpty ? Colors.black87 : Colors.black45)),
    ));
}
