package com.techguruji.smartschoolhub.utils;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;

import java.util.ArrayList;
import java.util.List;

/** Null-safe Gson accessors for the native API payloads. */
public final class J {

    private J() {}

    public static String s(JsonObject o, String k) {
        return s(o, k, "");
    }

    public static String s(JsonObject o, String k, String def) {
        if (o == null || !o.has(k) || o.get(k).isJsonNull()) return def;
        JsonElement e = o.get(k);
        if (e.isJsonPrimitive()) {
            if (e.getAsJsonPrimitive().isNumber()) {
                double d = e.getAsDouble();
                return d == Math.rint(d) ? String.valueOf((long) d) : String.valueOf(d);
            }
            return e.getAsString();
        }
        return e.toString();
    }

    public static int i(JsonObject o, String k) {
        return i(o, k, 0);
    }

    public static int i(JsonObject o, String k, int def) {
        if (o == null || !o.has(k) || o.get(k).isJsonNull()) return def;
        try {
            JsonElement e = o.get(k);
            if (e.isJsonPrimitive() && e.getAsJsonPrimitive().isBoolean()) return e.getAsBoolean() ? 1 : 0;
            String v = e.getAsString().trim();
            return v.isEmpty() ? def : (int) Double.parseDouble(v);
        } catch (Exception ex) {
            return def;
        }
    }

    public static double d(JsonObject o, String k) {
        if (o == null || !o.has(k) || o.get(k).isJsonNull()) return 0;
        try {
            String v = o.get(k).getAsString().trim();
            return v.isEmpty() ? 0 : Double.parseDouble(v);
        } catch (Exception ex) {
            return 0;
        }
    }

    public static boolean b(JsonObject o, String k) {
        if (o == null || !o.has(k) || o.get(k).isJsonNull()) return false;
        JsonElement e = o.get(k);
        if (e.isJsonPrimitive() && e.getAsJsonPrimitive().isBoolean()) return e.getAsBoolean();
        String v = e.isJsonPrimitive() ? e.getAsString() : "";
        return v.equals("1") || v.equalsIgnoreCase("true");
    }

    public static JsonObject o(JsonObject o, String k) {
        if (o == null || !o.has(k) || !o.get(k).isJsonObject()) return new JsonObject();
        return o.getAsJsonObject(k);
    }

    public static JsonArray a(JsonObject o, String k) {
        if (o == null || !o.has(k) || !o.get(k).isJsonArray()) return new JsonArray();
        return o.getAsJsonArray(k);
    }

    public static List<JsonObject> list(JsonObject o, String k) {
        return list(a(o, k));
    }

    public static List<JsonObject> list(JsonArray arr) {
        List<JsonObject> out = new ArrayList<>();
        if (arr == null) return out;
        for (JsonElement e : arr) if (e.isJsonObject()) out.add(e.getAsJsonObject());
        return out;
    }

    public static List<String> strings(JsonElement e) {
        List<String> out = new ArrayList<>();
        if (e == null || !e.isJsonArray()) return out;
        for (JsonElement x : e.getAsJsonArray()) {
            if (x.isJsonNull()) continue;
            out.add(x.isJsonPrimitive() ? x.getAsString() : x.toString());
        }
        return out;
    }

    public static List<String> strings(JsonObject o, String k) {
        return o == null ? new ArrayList<>() : strings(o.get(k));
    }

    /** Values of a {"1": "x", "2": "y"} style object in key order. */
    public static List<String> mapValues(JsonObject o) {
        List<String> out = new ArrayList<>();
        if (o == null) return out;
        for (String k : o.keySet()) out.add(s(o, k));
        return out;
    }

    public static JsonArray arr(List<String> items) {
        JsonArray a = new JsonArray();
        for (String s : items) a.add(s);
        return a;
    }

    public static JsonArray arrInt(List<Integer> items) {
        JsonArray a = new JsonArray();
        for (Integer s : items) a.add(s);
        return a;
    }

    public static int toInt(String s) {
        try {
            return (int) Double.parseDouble(s.trim());
        } catch (Exception e) {
            return 0;
        }
    }

    public static String fmt(double d) {
        return d == Math.rint(d) ? String.valueOf((long) d) : String.format(java.util.Locale.US, "%.2f", d);
    }
}
