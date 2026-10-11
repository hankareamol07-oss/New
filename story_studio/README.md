# Story Studio — गोष्ट व्हिडिओ बनवा

Adobe Animate (.fla) पात्रे + पार्श्वभूमी + मराठी/हिंदी/English आवाज → ॲनिमेटेड गोष्ट व्हिडिओ (MP4 + थंबनेल + SRT).

## सुरुवात (Start)
**सोपा मार्ग (Easy):** `StoryStudio_Windows.zip` unzip करा → `StoryStudio.exe` वर double-click. Python/ffmpeg लागत नाही (ffmpeg.exe सोबत आहे).

**Python मार्ग:**
1. Python 3.10+ आणि ffmpeg install करा (किंवा `ffmpeg.exe` या फोल्डरमध्ये ठेवा).
2. `INSTALL.bat` एकदा चालवा → `START_GUI.bat` ने ॲप उघडा. (`BUILD_EXE.bat` → `StoryStudio.exe`)

## वापर (How to use)
- **🎬 गोष्ट**: डावीकडे दृश्ये, मध्ये पूर्वदृश्य, खाली संवाद. "➕ पात्र जोडा" ने तयार पात्र निवडा, "📋 स्क्रिप्ट पेस्ट" (`नाव: संवाद`), मग **▶ व्हिडिओ बनवा**.
- आवाज आपोआप: पुरुष पात्राला पुरुष आवाज, स्त्रीला स्त्री आवाज (हवे तर बदला). मोफत Edge TTS — key लागत नाही.
- **🤖 AI गोष्ट**: विषय/तात्पर्य/धडा → AI गोष्ट लिहिते (सेटिंग्जमध्ये Gemini/NVIDIA/Groq key किंवा GCP key file).
- **🧍 पात्रे**: 65 तयार पात्रे. "📥 FLA आयात" ने तुमच्या .fla मधील पात्रे आपोआप rig होऊन जोडली जातात.
- **🦴 रिग**: डोके/तोंड/डोळे/हात layer व सांधे दुरुस्त करा, talk/point/walk पूर्वदृश्य पहा.
- **पार्श्वभूमी व संगीत**: 48 तयार पार्श्वभूमी (घर, शाळा, गाव, रेल्वे स्टेशन) व 10 BGM आधीच आहेत. तुमची चित्रे/फोल्डर/mp3 जोडा, किंवा **FLA मधून पार्श्वभूमी** ने background .fla मधील प्रत्येक दृश्य आयात करा. संगीत बोलताना आपोआप हळू होते.
- Shorts (9:16) पर्याय, कॅमेरा zoom/pan, fade/cut संक्रमण, चालत येणे/जाणे, बोट दाखवणे, हात हलवणे, विचार, नमस्कार.

## English
Paste or AI-generate a script, pick built-in characters, press ▶. Output goes to `output/` (MP4 1920×1080 or 1080×1920, 30 fps, H.264/AAC, JPG thumbnail, SRT).
Keys live only in `config.json` / `gcp_key.json` (both gitignored).
