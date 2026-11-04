package com.techguruji.smartschoolhub.data.network;

import androidx.annotation.NonNull;

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;

import java.util.HashMap;
import java.util.Map;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Thin wrapper over {@link NativeApi}: builds the query/body, runs the call and hands back
 * either the payload JsonObject or a Marathi/English error message from the PHP endpoint.
 */
public final class Native {

    public static final String CCE = "cce";
    public static final String HPC = "hpc";
    public static final String TACHAN = "tachan";

    public interface Cb {
        void ok(JsonObject data);

        void fail(String message);
    }

    private Native() {}

    /** Fluent parameter builder shared by GET and POST calls. */
    public static final class P {
        final JsonObject body = new JsonObject();

        public static P of(String action) {
            P p = new P();
            p.body.addProperty("action", action);
            return p;
        }

        public P put(String k, String v) {
            if (v != null) body.addProperty(k, v);
            return this;
        }

        public P put(String k, int v) {
            body.addProperty(k, v);
            return this;
        }

        public P put(String k, boolean v) {
            body.addProperty(k, v ? 1 : 0);
            return this;
        }

        public P put(String k, JsonElement v) {
            if (v != null) body.add(k, v);
            return this;
        }

        public Map<String, String> query() {
            Map<String, String> m = new HashMap<>();
            for (String k : body.keySet()) {
                JsonElement e = body.get(k);
                if (e.isJsonPrimitive()) m.put(k, e.getAsString());
                else m.put(k, e.toString());
            }
            return m;
        }

        public JsonObject json() {
            return body;
        }
    }

    public static void get(String module, P p, Cb cb) {
        NativeApi api = ApiClient.getInstance().getNativeApi();
        Call<JsonObject> call;
        switch (module) {
            case HPC:
                call = api.hpc(p.query());
                break;
            case TACHAN:
                call = api.tachan(p.query());
                break;
            default:
                call = api.cce(p.query());
        }
        run(call, cb);
    }

    public static void post(String module, P p, Cb cb) {
        NativeApi api = ApiClient.getInstance().getNativeApi();
        Call<JsonObject> call;
        switch (module) {
            case HPC:
                call = api.hpcPost(p.json());
                break;
            case TACHAN:
                call = api.tachanPost(p.json());
                break;
            default:
                call = api.ccePost(p.json());
        }
        run(call, cb);
    }

    private static void run(Call<JsonObject> call, Cb cb) {
        call.enqueue(new Callback<JsonObject>() {
            @Override
            public void onResponse(@NonNull Call<JsonObject> c, @NonNull Response<JsonObject> r) {
                JsonObject body = r.body();
                if (r.isSuccessful() && body != null) {
                    if (body.has("success") && !body.get("success").getAsBoolean()) {
                        cb.fail(msg(body, "त्रुटी आली"));
                    } else {
                        cb.ok(body);
                    }
                    return;
                }
                String m = "सर्व्हर त्रुटी (" + r.code() + ")";
                try {
                    if (r.errorBody() != null) {
                        JsonElement e = JsonParser.parseString(r.errorBody().string());
                        if (e.isJsonObject()) m = msg(e.getAsJsonObject(), m);
                    }
                } catch (Exception ignored) {
                }
                if (r.code() == 404 && m.startsWith("सर्व्हर")) {
                    m = "सर्व्हरवर नवीन API (api/*_native.php) अद्याप अपलोड केलेली नाही.";
                }
                cb.fail(m);
            }

            @Override
            public void onFailure(@NonNull Call<JsonObject> c, @NonNull Throwable t) {
                cb.fail("नेटवर्क त्रुटी: " + (t.getLocalizedMessage() == null ? "" : t.getLocalizedMessage()));
            }
        });
    }

    public static String msg(JsonObject o, String def) {
        return o != null && o.has("message") && !o.get("message").isJsonNull() ? o.get("message").getAsString() : def;
    }
}
