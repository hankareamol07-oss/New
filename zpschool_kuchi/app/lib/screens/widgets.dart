import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/session.dart';
import '../main.dart';
import '../theme.dart';
import 'in_app_web_screen.dart';

void toast(BuildContext c, String m, {bool error = false}) => ScaffoldMessenger.of(c).showSnackBar(
    SnackBar(content: Text(m), backgroundColor: error ? Colors.red.shade700 : TG.g800, behavior: SnackBarBehavior.floating));

Future<void> openWeb(String path, {BuildContext? context, String? title}) async {
  final targetContext = context ?? rootNavigatorKey.currentContext;
  if (targetContext != null) {
    Navigator.of(targetContext).push(
      MaterialPageRoute(
        builder: (_) => InAppWebScreen(url: path, title: title),
      ),
    );
    return;
  }
  final uri = Uri.parse(path.startsWith('http') ? path : '${Session.I.baseUrl}$path');
  await launchUrl(uri, mode: LaunchMode.inAppWebView);
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});
  final String text;
  final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 20, 4, 10),
        child: Row(children: [
          Container(width: 4, height: 20, decoration: BoxDecoration(color: TG.g600, borderRadius: BorderRadius.circular(2))),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: TG.inkA))),
          if (trailing != null) trailing!,
        ]),
      );
}

class IconTile extends StatelessWidget {
  const IconTile({super.key, required this.icon, required this.label, this.sub, this.color = TG.g600, this.onTap});
  final IconData icon;
  final String label;
  final String? sub;
  final Color color;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
              Container(
                width: 42, height: 42,
                decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(12)),
                child: Icon(icon, color: color),
              ),
              const SizedBox(height: 10),
              Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: TG.inkA)),
              if (sub != null) Text(sub!, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: TG.mutedA, fontSize: 12.5)),
            ]),
          ),
        ),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState(this.text, {super.key, this.icon = Icons.inbox_outlined});
  final String text;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 56, color: TG.mutedA),
            const SizedBox(height: 12),
            Text(text, textAlign: TextAlign.center, style: TextStyle(color: TG.mutedA, fontSize: 15)),
          ]),
        ),
      );
}

/// Gentle entrance animation: fade + slight rise, with an optional
/// stagger [delay]. Used across dashboards and lists for a pro feel.
class AnimIn extends StatefulWidget {
  const AnimIn({super.key, required this.child, this.delay = Duration.zero, this.dy = 16});
  final Widget child;
  final Duration delay;
  final double dy;
  @override
  State<AnimIn> createState() => _AnimInState();
}

class _AnimInState extends State<AnimIn> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 480));
  late final Animation<double> _fade =
      CurvedAnimation(parent: _c, curve: Curves.easeOutCubic);
  late final Animation<Offset> _slide = Tween<Offset>(
    begin: Offset(0, widget.dy / 100),
    end: Offset.zero,
  ).animate(CurvedAnimation(parent: _c, curve: Curves.easeOutCubic));

  @override
  void initState() {
    super.initState();
    if (widget.delay == Duration.zero) {
      _c.forward();
    } else {
      Future.delayed(widget.delay, () {
        if (mounted) _c.forward();
      });
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
        opacity: _fade,
        child: SlideTransition(position: _slide, child: widget.child),
      );
}
