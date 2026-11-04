package com.techguruji.smartschoolhub.data.model;

import com.google.gson.annotations.SerializedName;

public class ParipathData {

    @SerializedName("success")
    private boolean success;

    @SerializedName("date")
    private String date;

    @SerializedName("marathi_date")
    private String marathiDate;

    @SerializedName("day_name")
    private String dayName;

    @SerializedName("panchang")
    private Panchang panchang;

    @SerializedName("suvichar")
    private String suvichar;

    @SerializedName("suvichar_source")
    private String suvicharSource;

    @SerializedName("suvichar_meaning")
    private String suvicharMeaning;

    @SerializedName("dinvishesh")
    private String dinvishesh;

    @SerializedName("subhashit_text")
    private String subhashitText;

    @SerializedName("subhashit_meaning")
    private String subhashitMeaning;

    @SerializedName("prayer_text")
    private String prayerText;

    @SerializedName("national_anthem")
    private String nationalAnthem;

    @SerializedName("pledge")
    private String pledge;

    public boolean isSuccess() { return success; }
    public String getDate() { return date; }
    public String getMarathiDate() { return marathiDate; }
    public String getDayName() { return dayName; }
    public Panchang getPanchang() { return panchang; }
    public String getSuvichar() { return suvichar; }
    public String getSuvicharSource() { return suvicharSource; }
    public String getSuvicharMeaning() { return suvicharMeaning; }
    public String getDinvishesh() { return dinvishesh; }
    public String getSubhashitText() { return subhashitText; }
    public String getSubhashitMeaning() { return subhashitMeaning; }
    public String getPrayerText() { return prayerText; }
    public String getNationalAnthem() { return nationalAnthem; }
    public String getPledge() { return pledge; }

    public static class Panchang {
        @SerializedName("hindu_month")
        private String hinduMonth;

        @SerializedName("tithi")
        private String tithi;

        @SerializedName("nakshatra")
        private String nakshatra;

        @SerializedName("ritu")
        private String ritu;

        @SerializedName("paksha")
        private String paksha;

        @SerializedName("var")
        private String var;

        @SerializedName("sunrise")
        private String sunrise;

        @SerializedName("sunset")
        private String sunset;

        public String getHinduMonth() { return hinduMonth; }
        public String getTithi() { return tithi; }
        public String getNakshatra() { return nakshatra; }
        public String getRitu() { return ritu; }
        public String getPaksha() { return paksha; }
        public String getVar() { return var; }
        public String getSunrise() { return sunrise; }
        public String getSunset() { return sunset; }
    }
}
