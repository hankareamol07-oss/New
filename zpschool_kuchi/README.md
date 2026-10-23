# जि. प. शाळा कुची (zpschool Kuchi)

> **Z. P. School No. 2 Kuchi (UDISE: 27350302306)**  
> Comprehensive Smart School Management Portal & Flutter Android Mobile App.

---

## 📱 App Specifications & Branding

| Parameter | Value |
|---|---|
| **App Name (Marathi)** | **जि. प. शाळा कुची** |
| **App Name (English)** | **zpschool Kuchi** |
| **Android Package Name** | `com.zpschool.kuchi` |
| **Flutter SDK** | `>=3.5.4` |
| **Backend Base URL** | `http://52.66.73.149/zpschool2kuchi` |
| **Direct APK Download** | [http://52.66.73.149/zpschool2kuchi/downloads/zpschool2kuchi.apk](http://52.66.73.149/zpschool2kuchi/downloads/zpschool2kuchi.apk) |

---

## 📁 Repository Structure

```
zpschool_kuchi/
├── app/                  # Flutter Android/iOS cross-platform mobile application
│   ├── android/          # Android native project (package: com.zpschool.kuchi)
│   ├── lib/              # Flutter Dart source code
│   │   ├── core/         # Config, Session, Local SQLite DB, Sync & API engine
│   │   ├── screens/      # All UI Screens (Login, In-App WebView, Dashboard, CCE, Tachan)
│   │   └── main.dart     # App entry point
│   └── pubspec.yaml      # Flutter dependencies
├── portal/               # PHP 8.x Web Portal & REST APIs
│   ├── api/              # JSON REST API endpoints (auth, easy_login, daily_study, quiz)
│   ├── cce/              # Continuous Comprehensive Evaluation (CCE marks & annual reports)
│   ├── hpc/              # Holistic Progress Card module
│   ├── modules/          # Tachan (daily planning), Quiz (SQLite), Daily Study
│   └── index.php         # Student & Teacher responsive web interface
└── database/             # Database schemas, migrations, and seeds
```

---

## 🚀 Key Features

1. **In-App WebView Navigation:**
   - All modules, student dashboards, AI study bots, and external links load securely inside the app using `webview_flutter`.
   - Never redirects outside to third-party browsers like Chrome.
   - Preserves active authentication tokens and session cookies across internal pages.
   - Built-in hardware back-button handler (`PopScope`) and navigation controls.

2. **Dual Login Modes:**
   - **Student Easy Login:** Choose Class (इयत्ता १ ली ते ८ वी) -> Select Student Name -> Enter PIN (`123456`).
   - **Teacher / Staff Login:** Enter Mobile number -> Enter Password (`123456`).
   - Sibling switcher: Multi-child family profile switching without re-entering credentials.

3. **Academic Modules:**
   - CCE Marks Entry & Formative/Summative evaluation with dual-class teaching support (Std 6 & 7).
   - Tachan Weekly Timetable linked with daily planning.
   - Interactive Marathi Quiz with 46 subject topics.
   - AI Study Bot (AI अभ्यास मित्र).

---

## 🤖 Muse AI Prompt to Build APK & Fix Bugs

Copy and paste the exact prompt below into Muse AI:

```markdown
You are an expert Flutter and Android engineer.
Please inspect and build the Flutter mobile application located in this repository at `zpschool_kuchi/app`.

### App Configuration Requirements:
1. Target Directory: `zpschool_kuchi/app`
2. App Name:
   - Marathi: "जि. प. शाळा कुची"
   - English: "zpschool Kuchi"
3. Android Package Name: `com.zpschool.kuchi`
   - namespace: `com.zpschool.kuchi` in `android/app/build.gradle.kts`
   - applicationId: `com.zpschool.kuchi` in `android/app/build.gradle.kts`
   - MainActivity: `android/app/src/main/kotlin/com/zpschool/kuchi/MainActivity.kt`
4. Backend API Base URL: `http://52.66.73.149/zpschool2kuchi` (configured in `lib/core/config.dart`).
5. WebView Behavior:
   - Ensure all web links, student progress portals, AI study bot, and privacy links open INSIDE the Flutter app (`InAppWebScreen` / `webview_flutter`).
   - No link should launch an external browser (e.g. Chrome via url_launcher external application mode).
   - Support back button navigation within the webview.
6. Login Verification:
   - Student Easy Login: Std 1-8 picker, name selector, default PIN 123456.
   - Teacher Login: Mobile + PIN 123456.

### Build Tasks:
1. Run `flutter clean` and `flutter pub get`.
2. Run `flutter analyze` and resolve any warnings or deprecated APIs.
3. Build the release APK:
   `flutter build apk --release`
4. Provide the generated APK path in `build/app/outputs/flutter-apk/app-release.apk`.
```

---

## 🛠️ Local Flutter Build Commands

```bash
cd zpschool_kuchi/app
flutter pub get
flutter analyze
flutter build apk --release
```
