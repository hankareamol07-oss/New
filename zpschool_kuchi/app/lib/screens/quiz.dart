import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../theme.dart';
import 'widgets.dart';

/// Native quiz section in the style of the portal's LPK Web Quiz:
/// subject → topic list, a game-style play screen with a countdown ring,
/// and a result screen with stars + full answer review.
/// Data comes from the portal's own api/quiz.php (dashboard / get / submit).

Color _hexColor(String? hex, Color fallback) {
  if (hex == null || hex.isEmpty) return fallback;
  try {
    return Color(int.parse(hex.replaceFirst('#', '0xFF')));
  } catch (_) {
    return fallback;
  }
}

class QuizHomeScreen extends StatefulWidget {
  const QuizHomeScreen({super.key});
  @override
  State<QuizHomeScreen> createState() => _QuizHomeScreenState();
}

class _QuizHomeScreenState extends State<QuizHomeScreen> {
  late int _std;
  List _subjects = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    final u = Session.I.user;
    _std = Session.I.role == 'student' ? (int.tryParse('${u['std'] ?? 6}') ?? 6) : 6;
    if (_std < 1 || _std > 8) _std = 6;
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final d = await Api.get('quiz.php', {'action': 'dashboard', 'std': '$_std'});
      if (mounted) {
        setState(() {
          _subjects = (d['subjects'] as List?) ?? [];
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('🎯 क्विझ खेळ')),
      body: Column(children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Wrap(spacing: 8, runSpacing: 4, children: [
            for (var i = 1; i <= 8; i++)
              ChoiceChip(
                label: Text('इयत्ता $i'),
                selected: _std == i,
                onSelected: (_) {
                  setState(() => _std = i);
                  _load();
                },
              ),
          ]),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(Icons.wifi_off, size: 48, color: Colors.orange),
                          const SizedBox(height: 12),
                          Text(_error!, textAlign: TextAlign.center),
                          const SizedBox(height: 16),
                          FilledButton.icon(
                            onPressed: _load,
                            icon: const Icon(Icons.refresh),
                            label: const Text('पुन्हा प्रयत्न करा'),
                          ),
                        ]),
                      ),
                    )
                  : _subjects.isEmpty
                      ? const EmptyState('या इयत्तेसाठी सध्या क्विझ उपलब्ध नाहीत.', icon: Icons.quiz_outlined)
                      : ListView(
                          padding: const EdgeInsets.all(16),
                          children: [
                            for (var si = 0; si < _subjects.length; si++) ...[
                              AnimIn(
                                delay: Duration(milliseconds: 60 * si),
                                child: Row(children: [
                                  Container(
                                    width: 14,
                                    height: 14,
                                    decoration: BoxDecoration(
                                      color: _hexColor('${_subjects[si]['color']}', TG.g600),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text('${_subjects[si]['name']}',
                                        style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                                  ),
                                ]),
                              ),
                              const SizedBox(height: 10),
                              for (final t in (_subjects[si]['topics'] as List? ?? []))
                                AnimIn(
                                  delay: const Duration(milliseconds: 40),
                                  child: Card(
                                    margin: const EdgeInsets.only(bottom: 10),
                                    child: InkWell(
                                      borderRadius: BorderRadius.circular(16),
                                      onTap: () => Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                            builder: (_) => QuizPlayScreen(quiz: Map<String, dynamic>.from(t as Map))),
                                      ),
                                      child: Padding(
                                        padding: const EdgeInsets.all(14),
                                        child: Row(children: [
                                          Container(
                                            width: 46,
                                            height: 46,
                                            decoration: BoxDecoration(
                                              color: _hexColor('${_subjects[si]['color']}', TG.g600).withValues(alpha: .13),
                                              borderRadius: BorderRadius.circular(12),
                                            ),
                                            child: Icon(Icons.quiz_outlined,
                                                color: _hexColor('${_subjects[si]['color']}', TG.g600)),
                                          ),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                              Text('${t['title']}',
                                                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5)),
                                              const SizedBox(height: 2),
                                              Text(
                                                '${t['total_questions']} प्रश्न • ⏱ ${t['time_limit_minutes']} मि • उत्तीर्ण ${t['pass_percentage']}%',
                                                style: TextStyle(color: TG.mutedA, fontSize: 12.5),
                                              ),
                                              if (t['best_score'] != null)
                                                Padding(
                                                  padding: const EdgeInsets.only(top: 3),
                                                  child: Text('⭐ सर्वोत्तम गुण: ${t['best_score']}/${t['total_questions']}',
                                                      style: const TextStyle(color: TG.g600, fontSize: 12.5, fontWeight: FontWeight.w700)),
                                                ),
                                            ]),
                                          ),
                                          const Icon(Icons.play_circle_fill, color: TG.g600, size: 30),
                                        ]),
                                      ),
                                    ),
                                  ),
                                ),
                              const SizedBox(height: 8),
                            ],
                          ],
                        ),
        ),
      ]),
    );
  }
}

class QuizPlayScreen extends StatefulWidget {
  const QuizPlayScreen({super.key, required this.quiz});
  final Map<String, dynamic> quiz;
  @override
  State<QuizPlayScreen> createState() => _QuizPlayScreenState();
}

class _QuizPlayScreenState extends State<QuizPlayScreen> {
  List _questions = [];
  final Map<int, String> _answers = {};
  int _i = 0;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  int _secondsLeft = 0;
  int _totalSeconds = 0;
  Timer? _timer;

  static const _letters = ['A', 'B', 'C', 'D'];
  static const _optKeys = ['option_a', 'option_b', 'option_c', 'option_d'];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final d = await Api.get('quiz.php', {'action': 'get', 'id': '${widget.quiz['id']}'});
      final q = (d['quiz'] as Map?) ?? {};
      final mins = int.tryParse('${q['time_limit_minutes'] ?? widget.quiz['time_limit_minutes'] ?? 15}') ?? 15;
      if (mounted) {
        setState(() {
          _questions = (q['questions'] as List?) ?? [];
          _totalSeconds = _secondsLeft = mins * 60;
          _loading = false;
        });
        _startTimer();
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    }
  }

  void _startTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return;
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) {
        t.cancel();
        _submit(auto: true);
      }
    });
  }

  String get _clock {
    final s = _secondsLeft < 0 ? 0 : _secondsLeft;
    return '${(s ~/ 60).toString().padLeft(2, '0')}:${(s % 60).toString().padLeft(2, '0')}';
  }

  Color get _timerColor {
    if (_totalSeconds == 0) return TG.g600;
    final f = _secondsLeft / _totalSeconds;
    return f > 0.5 ? TG.g600 : (f > 0.25 ? const Color(0xFFD97706) : Colors.red);
  }

  Future<void> _submit({bool auto = false}) async {
    if (_submitting) return;
    if (!auto && _answers.length < _questions.length) {
      final go = await showDialog<bool>(
        context: context,
        builder: (c) => AlertDialog(
          title: const Text('सबमिट करायचे?'),
          content: Text('${_questions.length - _answers.length} प्रश्नांची उत्तरे दिलेली नाहीत. तरी सबमिट करायचे?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('नाही')),
            FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('हो, सबमिट')),
          ],
        ),
      );
      if (go != true) return;
    }
    _timer?.cancel();
    setState(() => _submitting = true);
    try {
      final r = await Api.post('quiz.php', {
        'action': 'submit',
        'quiz_id': widget.quiz['id'],
        'answers': {for (final e in _answers.entries) '${e.key}': e.value},
        'time_spent': _totalSeconds - (_secondsLeft < 0 ? 0 : _secondsLeft),
      });
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => QuizResultScreen(
            result: Map<String, dynamic>.from(r['result'] as Map),
            quiz: widget.quiz,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _submitting = false);
        toast(context, e.message, error: true);
        _startTimer();
      }
    }
  }

  Future<bool> _confirmExit() async {
    final go = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('क्विझ सोडायची?'),
        content: const Text('तुमची उत्तरे जतन होणार नाहीत.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('नाही')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('हो, सोडा')),
        ],
      ),
    );
    return go == true;
  }

  @override
  Widget build(BuildContext context) {
    final title = '${widget.quiz['title'] ?? 'क्विझ'}';
    if (_loading) {
      return Scaffold(appBar: AppBar(title: Text(title)), body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null || _questions.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: Text(title)),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.quiz_outlined, size: 48, color: TG.muted),
              const SizedBox(height: 12),
              Text(_error ?? 'या क्विझमध्ये प्रश्न उपलब्ध नाहीत.', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(onPressed: () => Navigator.pop(context), child: const Text('मागे जा')),
            ]),
          ),
        ),
      );
    }
    final q = _questions[_i] as Map;
    final qid = int.tryParse('${q['id']}') ?? 0;
    final selected = _answers[qid];
    final frac = _totalSeconds == 0 ? 0.0 : _secondsLeft / _totalSeconds;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _confirmExit() && context.mounted) Navigator.of(context).pop();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(title, style: const TextStyle(fontSize: 17)),
          leading: IconButton(
            icon: const Icon(Icons.close),
            onPressed: () async {
              if (await _confirmExit() && context.mounted) Navigator.of(context).pop();
            },
          ),
        ),
        body: Column(children: [
          // Game status bar (LPK style)
          Container(
            margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: TG.lineA),
            ),
            child: Row(children: [
              SizedBox(
                width: 52,
                height: 52,
                child: Stack(fit: StackFit.expand, children: [
                  CircularProgressIndicator(value: frac.toDouble(), strokeWidth: 5, backgroundColor: TG.lineA, color: _timerColor),
                  Center(child: Text(_clock, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 11.5, color: _timerColor))),
                ]),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('प्रश्न ${_i + 1} / ${_questions.length}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                  const SizedBox(height: 5),
                  LinearProgressIndicator(
                    value: (_i + 1) / _questions.length,
                    backgroundColor: TG.lineA,
                    color: TG.g600,
                    borderRadius: BorderRadius.circular(6),
                    minHeight: 8,
                  ),
                ]),
              ),
              const SizedBox(width: 12),
              Column(children: [
                const Icon(Icons.check_circle_outline, color: TG.g600, size: 20),
                Text('${_answers.length} उत्तरे', style: TextStyle(fontSize: 11.5, color: TG.mutedA, fontWeight: FontWeight.w700)),
              ]),
            ]),
          ),
          Expanded(
            child: ListView(padding: const EdgeInsets.all(16), children: [
              AnimIn(
                key: ValueKey(_i),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Text('${q['question_text'] ?? ''}',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, height: 1.45)),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              for (var o = 0; o < 4; o++)
                AnimIn(
                  key: ValueKey('$_i-$o'),
                  delay: Duration(milliseconds: 60 * o),
                  child: Card(
                    margin: const EdgeInsets.only(bottom: 10),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                      side: BorderSide(
                        color: selected == _letters[o] ? TG.g600 : TG.lineA,
                        width: selected == _letters[o] ? 2 : 1,
                      ),
                    ),
                    color: selected == _letters[o] ? TG.g50A : null,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(14),
                      onTap: () => setState(() => _answers[qid] = _letters[o]),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                        child: Row(children: [
                          Container(
                            width: 34,
                            height: 34,
                            decoration: BoxDecoration(
                              color: selected == _letters[o] ? TG.g600 : TG.g50A,
                              shape: BoxShape.circle,
                            ),
                            child: Center(
                              child: Text(_letters[o],
                                  style: TextStyle(
                                    fontWeight: FontWeight.w800,
                                    color: selected == _letters[o] ? Colors.white : TG.g600,
                                  )),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(child: Text('${q[_optKeys[o]] ?? ''}', style: const TextStyle(fontSize: 15.5, height: 1.35))),
                          if (selected == _letters[o]) const Icon(Icons.check_circle, color: TG.g600),
                        ]),
                      ),
                    ),
                  ),
                ),
            ]),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
              child: Row(children: [
                if (_i > 0)
                  OutlinedButton.icon(
                    onPressed: () => setState(() => _i--),
                    icon: const Icon(Icons.arrow_back, size: 18),
                    label: const Text('मागील'),
                  ),
                if (_i > 0) const SizedBox(width: 10),
                Expanded(
                  child: _i < _questions.length - 1
                      ? FilledButton.icon(
                          onPressed: () => setState(() => _i++),
                          icon: const Icon(Icons.arrow_forward, size: 18),
                          label: const Text('पुढील प्रश्न'),
                        )
                      : FilledButton.icon(
                          onPressed: _submitting ? null : () => _submit(),
                          icon: _submitting
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Icon(Icons.check, size: 18),
                          label: const Text('सबमिट करा'),
                        ),
                ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class QuizResultScreen extends StatelessWidget {
  const QuizResultScreen({super.key, required this.result, required this.quiz});
  final Map<String, dynamic> result;
  final Map<String, dynamic> quiz;

  static const _letters = ['A', 'B', 'C', 'D'];
  static const _optKeys = ['option_a', 'option_b', 'option_c', 'option_d'];

  String _optText(Map r, String letter) {
    final idx = _letters.indexOf(letter.toUpperCase());
    if (idx < 0) return '—';
    return '${r[_optKeys[idx]] ?? ''}';
  }

  @override
  Widget build(BuildContext context) {
    final pct = (result['percentage'] as num?)?.toDouble() ?? 0;
    final passed = result['status'] == 'passed';
    final stars = pct >= 90 ? 3 : (pct >= 60 ? 2 : (passed ? 1 : 0));
    final review = (result['review'] as List?) ?? [];
    return Scaffold(
      appBar: AppBar(title: const Text('🏆 निकाल')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        AnimIn(
          child: Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: passed ? const [TG.g900, TG.g600] : const [Color(0xFF7C2D12), Color(0xFFD97706)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Column(children: [
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                for (var s = 0; s < 3; s++)
                  Icon(
                    s < stars ? Icons.star : Icons.star_border,
                    color: const Color(0xFFFFD54F),
                    size: s == 1 ? 40 : 30,
                  ),
              ]),
              const SizedBox(height: 10),
              SizedBox(
                width: 104,
                height: 104,
                child: Stack(fit: StackFit.expand, children: [
                  CircularProgressIndicator(
                    value: pct / 100,
                    strokeWidth: 9,
                    backgroundColor: Colors.white24,
                    color: Colors.white,
                  ),
                  Center(
                    child: Text('${pct.toStringAsFixed(pct % 1 == 0 ? 0 : 1)}%',
                        style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
                  ),
                ]),
              ),
              const SizedBox(height: 12),
              Text(passed ? '🎉 अभिनंदन! तुम्ही उत्तीर्ण झालात' : '💪 अजून थोडा सराव करा',
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                  textAlign: TextAlign.center),
              const SizedBox(height: 4),
              Text('गुण: ${result['score']} / ${result['total']} • ${quiz['title'] ?? ''}',
                  style: const TextStyle(color: Color(0xFFCFE7DC), fontSize: 13.5), textAlign: TextAlign.center),
            ]),
          ),
        ),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (_) => QuizPlayScreen(quiz: quiz)),
              ),
              icon: const Icon(Icons.replay),
              label: const Text('पुन्हा खेळा'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: FilledButton.icon(
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.list_alt),
              label: const Text('क्विझ यादी'),
            ),
          ),
        ]),
        const SectionTitle('उत्तरे तपासा'),
        for (var i = 0; i < review.length; i++)
          Builder(builder: (_) {
            final r = review[i] as Map;
            final ok = r['is_correct'] == true;
            final ua = '${r['user_answer'] ?? ''}'.toUpperCase();
            final ca = '${r['correct_answer'] ?? ''}'.toUpperCase();
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Icon(ok ? Icons.check_circle : Icons.cancel, color: ok ? TG.g600 : Colors.red, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text('${i + 1}. ${r['question_text'] ?? ''}',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5, height: 1.4)),
                    ),
                  ]),
                  const SizedBox(height: 8),
                  Text(
                    ua.isEmpty ? 'तुमचे उत्तर: दिले नाही' : 'तुमचे उत्तर: $ua) ${_optText(r, ua)}',
                    style: TextStyle(color: ok ? TG.g600 : Colors.red, fontWeight: FontWeight.w600, fontSize: 13.5),
                  ),
                  if (!ok)
                    Text('बरोबर उत्तर: $ca) ${_optText(r, ca)}',
                        style: const TextStyle(color: TG.g600, fontWeight: FontWeight.w600, fontSize: 13.5)),
                  if ('${r['explanation'] ?? ''}'.trim().isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text('💡 ${r['explanation']}',
                          style: TextStyle(color: TG.mutedA, fontSize: 13, height: 1.4)),
                    ),
                ]),
              ),
            );
          }),
        const SizedBox(height: 12),
      ]),
    );
  }
}
