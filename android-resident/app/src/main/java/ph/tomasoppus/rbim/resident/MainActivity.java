package ph.tomasoppus.rbim.resident;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.BitmapFactory;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.provider.Settings;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.widget.*;
import org.json.*;
import java.io.InputStream;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;
import java.util.function.Consumer;

/** Native Android resident client. No embedded browser or staff screens. */
public class MainActivity extends Activity {
    private static final int NAVY = Color.rgb(22,56,82), TEAL = Color.rgb(36,107,94), CANVAS = Color.rgb(243,246,250), MUTED = Color.rgb(92,112,131);
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private SessionStore sessions;
    private String server, token = "", tab = "Home";
    private JSONObject user;
    private LinearLayout root, body, navigation;
    private TextView progress;
    private Runnable backAction;
    private Consumer<Uri> fileReceiver;
    private int screenId;
    private boolean busy;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        if (!BuildConfig.DEBUG) getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        sessions = new SessionStore(this);
        server = BuildConfig.DEBUG ? getPreferences(0).getString("server", "http://10.0.2.2:8000") : BuildConfig.SERVER_URL;
        token = sessions.read();
        if (token.isEmpty()) login(); else account();
    }

    private int dp(float value) { return (int)(value * getResources().getDisplayMetrics().density + .5f); }
    private GradientDrawable surface(int color, int radius) {
        GradientDrawable shape = new GradientDrawable(); shape.setColor(color); shape.setCornerRadius(dp(radius)); return shape;
    }
    private LinearLayout column() { LinearLayout layout = new LinearLayout(this); layout.setOrientation(LinearLayout.VERTICAL); return layout; }
    private TextView text(LinearLayout parent, String value, int size, int color, boolean bold) {
        TextView view = new TextView(this); view.setText(value); view.setTextSize(size); view.setTextColor(color);
        view.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        view.setLineSpacing(dp(3), 1); view.setPadding(0, dp(4), 0, dp(6)); parent.addView(view); return view;
    }
    private LinearLayout card() {
        LinearLayout panel = column(); panel.setPadding(dp(18), dp(14), dp(18), dp(16)); panel.setBackground(surface(Color.WHITE, 16));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(-1, -2); params.bottomMargin = dp(16); body.addView(panel, params); return panel;
    }
    private Button button(LinearLayout parent, String label, Runnable action) {
        Button view = new Button(this); view.setText(label); view.setAllCaps(false); view.setTextSize(14); view.setTextColor(Color.WHITE);
        view.setBackgroundTintList(android.content.res.ColorStateList.valueOf(TEAL));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(-1, -2); params.topMargin = dp(6);
        view.setMinHeight(dp(48)); parent.addView(view, params); view.setOnClickListener(v -> { if (!busy) action.run(); }); return view;
    }
    private EditText field(LinearLayout parent, String label, int type) {
        TextView caption = text(parent, label, 12, NAVY, true);
        EditText input = new EditText(this); input.setId(View.generateViewId()); caption.setLabelFor(input.getId());
        input.setInputType(type); input.setTextSize(15); input.setTextColor(NAVY); input.setHintTextColor(MUTED); input.setHint(label);
        input.setPadding(dp(12), dp(12), dp(12), dp(12)); input.setMinHeight(dp(48));
        input.setBackground(surface(CANVAS, 9)); input.setSaveEnabled(false);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(-1, -2); params.bottomMargin = dp(12); parent.addView(input, params); return input;
    }
    private EditText password(LinearLayout panel, String label) { return field(panel, label, InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD); }
    private String value(EditText input) { return input.getText().toString(); }
    private JSONObject json(Object... pairs) {
        JSONObject object = new JSONObject();
        try { for (int i = 0; i < pairs.length; i += 2) object.put((String)pairs[i], pairs[i + 1]); } catch (JSONException e) { throw new IllegalArgumentException(e); }
        return object;
    }
    private String str(JSONObject data, String key) { return data == null || data.isNull(key) ? "" : data.optString(key, ""); }
    private void screen(String title, String subtitle, boolean signedIn, Runnable back) {
        View focused = getCurrentFocus();
        if (focused != null) ((android.view.inputmethod.InputMethodManager)getSystemService(INPUT_METHOD_SERVICE)).hideSoftInputFromWindow(focused.getWindowToken(), 0);
        screenId++; backAction = back;
        root = column(); root.setBackgroundColor(CANVAS); setContentView(root);
        root.setOnApplyWindowInsetsListener((view, insets) -> {
            view.setPadding(insets.getSystemWindowInsetLeft(), insets.getSystemWindowInsetTop(), insets.getSystemWindowInsetRight(), insets.getSystemWindowInsetBottom()); return insets;
        });
        root.requestApplyInsets();
        LinearLayout heading = column(); heading.setPadding(dp(22), dp(16), dp(22), dp(18));
        heading.setBackground(new GradientDrawable(GradientDrawable.Orientation.LEFT_RIGHT, new int[]{NAVY, TEAL}));
        text(heading, "TOMAS OPPUS  /  RBIM RESIDENT", 10, Color.WHITE, true);
        text(heading, title, 25, Color.WHITE, true);
        if (!subtitle.isEmpty()) text(heading, subtitle, 13, Color.WHITE, false);
        root.addView(heading);
        progress = new TextView(this); progress.setText("Connecting…"); progress.setTextColor(TEAL); progress.setTextSize(12); progress.setPadding(dp(20), dp(8), dp(20), dp(8));
        progress.setVisibility(busy ? View.VISIBLE : View.GONE); root.addView(progress);
        ScrollView scroll = new ScrollView(this); scroll.setFillViewport(true); root.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1));
        body = column(); body.setPadding(dp(18), dp(20), dp(18), dp(14)); scroll.addView(body);
        navigation = new LinearLayout(this); navigation.setBackgroundColor(Color.WHITE);
        if (signedIn) {
            String[] tabs = {"Home", "Requests", "Concerns", "Profile"};
            for (String item : tabs) {
                TextView link = new TextView(this); link.setText(item); link.setTextSize(12); link.setGravity(Gravity.CENTER); link.setMinHeight(dp(56));
                link.setTextColor(item.equals(tab) ? TEAL : MUTED); link.setTypeface(null, item.equals(tab) ? Typeface.BOLD : Typeface.NORMAL);
                link.setContentDescription(item + (item.equals(tab) ? ", selected" : "")); link.setSelected(item.equals(tab));
                navigation.addView(link, new LinearLayout.LayoutParams(0, -2, 1));
                link.setOnClickListener(v -> { if (!busy) navigate(item); });
            }
            root.addView(navigation);
        }
        float scale = Settings.Global.getFloat(getContentResolver(), Settings.Global.ANIMATOR_DURATION_SCALE, 1);
        if (scale > 0) { body.setAlpha(.4f); body.animate().alpha(1).setDuration(180).start(); }
    }
    private void navigate(String item) { tab = item; switch (item) { case "Requests": listing(false, 1); break; case "Concerns": listing(true, 1); break; case "Profile": profile(); break; default: home(); } }
    private void busy(boolean active) { busy = active; if (progress != null) progress.setVisibility(active ? View.VISIBLE : View.GONE); setEnabled(body, !active); setEnabled(navigation, !active); }
    private void setEnabled(View view, boolean enabled) { if (view == null) return; view.setEnabled(enabled); if (view instanceof ViewGroup) for (int i=0;i<((ViewGroup)view).getChildCount();i++) setEnabled(((ViewGroup)view).getChildAt(i), enabled); }
    private void alert(String title, String message) { new AlertDialog.Builder(this).setTitle(title).setMessage(message).setPositiveButton("OK", null).show(); }
    private interface Work<T> { T run() throws Exception; }
    private <T> void work(Work<T> task, Consumer<T> success, boolean mutation) {
        if (busy) return;
        int revision = screenId; busy(true);
        executor.execute(() -> {
            try { T result = task.run(); runOnUiThread(() -> { if (!isDestroyed() && revision == screenId) { busy(false); success.accept(result); } }); }
            catch (Exception error) { runOnUiThread(() -> {
                if (isDestroyed() || revision != screenId) return;
                busy(false);
                if (error instanceof ResidentApi.Failure) {
                    ResidentApi.Failure failure = (ResidentApi.Failure) error;
                    if (failure.status == 401) { clearSession(); login(); }
                    alert("Unable to continue", failure.getMessage());
                } else alert("Connection or file problem", "Check your server address, Wi-Fi and selected file. " + (mutation ? "If you were submitting, check your request list before trying again to avoid duplicates." : "Please try again."));
            }); }
        });
    }
    private void call(String method, String path, JSONObject data, Consumer<JSONObject> success) {
        String base = server, credential = token;
        work(() -> ResidentApi.request(base, credential, method, path, data, null, null, null), success, !method.equals("GET"));
    }
    private void clearSession() { sessions.clear(); token = ""; user = null; }
    private void acceptSession(JSONObject data) {
        try { token = data.getString("token"); sessions.save(token); user = data.getJSONObject("user"); showAccount(); }
        catch (Exception error) { clearSession(); login(); alert("Sign-in unavailable", "Unable to store a secure session on this device. Please try again."); }
    }
    private void login() {
        tab = "Home"; screen("Welcome back", "Sign in to your resident account.", false, null);
        LinearLayout panel = card(); text(panel, "Resident sign in", 19, NAVY, true);
        EditText email = field(panel, "Email address", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_EMAIL_ADDRESS);
        EditText pass = password(panel, "Password");
        button(panel, "Sign in", () -> call("POST", "login", json("email", value(email).trim(), "password", value(pass)), this::acceptSession));
        button(panel, "Create resident account", this::register);
        text(panel, "Barangay and municipal staff use the RBIM website.", 12, MUTED, false);
        if (BuildConfig.DEBUG) {
            LinearLayout settings = card(); text(settings, "Local testing", 15, NAVY, true); text(settings, server, 12, MUTED, false);
            button(settings, "Set server address", this::serverSettings);
        }
    }
    private void serverSettings() {
        if (!BuildConfig.DEBUG) return;
        EditText address = new EditText(this); address.setSingleLine(true); address.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI); address.setText(server);
        AlertDialog dialog = new AlertDialog.Builder(this).setTitle("Laragon server address").setMessage("Use your PC's Wi-Fi IP, for example http://192.168.1.10:8000. Keep both devices on the same Wi-Fi.")
            .setView(address).setNegativeButton("Cancel", null).setPositiveButton("Save", null).create();
        dialog.setOnShowListener(d -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
            try { String next = ResidentApi.server(value(address)); clearSession(); server = next; getPreferences(0).edit().putString("server", server).apply(); dialog.dismiss(); login(); }
            catch (Exception error) { address.setError(error.getMessage()); }
        })); dialog.show();
    }
    private Spinner choices(LinearLayout panel, String label, JSONArray items, String labelKey) {
        TextView caption = text(panel, label, 12, NAVY, true);
        Spinner spinner = new Spinner(this); spinner.setId(View.generateViewId()); caption.setLabelFor(spinner.getId());
        String[] labels = new String[items.length()];
        for (int i=0;i<items.length();i++) labels[i] = items.optJSONObject(i).optString(labelKey);
        ArrayAdapter<String> adapter = new ArrayAdapter<>(this, android.R.layout.simple_spinner_item, labels); adapter.setDropDownViewResource(android.R.layout.simple_spinner_dropdown_item);
        spinner.setAdapter(adapter); spinner.setMinimumHeight(dp(48)); panel.addView(spinner); return spinner;
    }
    private void register() {
        screen("Create an account", "Your barangay will verify your registration.", false, this::login);
        call("GET", "barangays", null, result -> {
            JSONArray areas = result.optJSONArray("data");
            if (areas == null || areas.length() == 0) { alert("Registration unavailable", "No barangays are configured. Contact the municipality."); login(); return; }
            LinearLayout panel = card();
            EditText name = field(panel, "Full name as recorded in the RBI", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_WORDS);
            EditText email = field(panel, "Email address", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_EMAIL_ADDRESS);
            Spinner area = choices(panel, "Your barangay", areas, "name");
            EditText pass = password(panel, "Password (at least 8 characters)"), confirm = password(panel, "Confirm password");
            button(panel, "Submit registration", () -> call("POST", "register", json("name", value(name).trim(), "email", value(email).trim(),
                "barangay_id", areas.optJSONObject(area.getSelectedItemPosition()).optInt("id"), "password", value(pass), "password_confirmation", value(confirm)), this::acceptSession));
            button(panel, "Back to sign in", this::login);
        });
    }
    private void account() {
        screen("Your account", "Checking your barangay approval…", false, null);
        call("GET", "me", null, result -> { user = result.optJSONObject("user"); showAccount(); });
        button(body, "Try again", this::account);
        button(body, "Sign out", this::logout);
    }
    private void showAccount() {
        if (user != null && "approved".equals(str(user, "approval_status")) && user.optJSONObject("barangay") != null) { home(); return; }
        screen("Account verification", "Your resident registration", false, null);
        LinearLayout panel = card(); boolean rejected = "rejected".equals(str(user, "approval_status"));
        text(panel, rejected ? "Registration rejected" : "Waiting for barangay approval", 20, NAVY, true);
        text(panel, rejected ? "Please contact your barangay office about your registration." : "Your account is registered. Your barangay must verify your residency before you can submit requests or concerns.", 15, MUTED, false);
        button(panel, "Check approval status", this::account); button(panel, "Sign out", this::logout);
    }
    private void home() {
        tab = "Home"; screen("Your community, connected", "Resident services in one place.", true, null);
        call("GET", "dashboard", null, result -> {
            user = result.optJSONObject("user"); JSONObject counts = result.optJSONObject("summary");
            LinearLayout welcome = card(); text(welcome, "Good day, " + str(user, "name"), 21, NAVY, true);
            text(welcome, "Barangay " + str(user.optJSONObject("barangay"), "name"), 14, TEAL, true);
            text(welcome, "Track your documents and stay in touch with your barangay.", 14, MUTED, false);
            LinearLayout summary = card(); text(summary, "Your service summary", 17, NAVY, true);
            text(summary, counts.optInt("documents") + " document requests", 18, NAVY, true);
            text(summary, counts.optInt("ready") + " ready for release", 16, TEAL, true);
            text(summary, counts.optInt("active_concerns") + " active concerns", 16, NAVY, false);
            button(summary, "Request a document", () -> create(false)); button(summary, "Report a concern", () -> create(true));
            button(body, "Refresh updates", this::account);
        });
    }
    private void listing(boolean concerns, int page) {
        tab = concerns ? "Concerns" : "Requests"; screen(concerns ? "My concerns" : "My document requests", "Updates from your barangay.", true, this::home);
        call("GET", (concerns ? "concerns" : "documents") + "?page=" + page, null, result -> {
            button(body, concerns ? "Submit a concern" : "Request a document", () -> create(concerns));
            JSONArray items = result.optJSONArray("data");
            if (items == null || items.length() == 0) { LinearLayout empty = card(); text(empty, "Nothing here yet", 20, NAVY, true); text(empty, "Your submissions and their latest status will appear here.", 14, MUTED, false); }
            else for (int i=0;i<items.length();i++) {
                JSONObject item = items.optJSONObject(i); LinearLayout panel = card(); int id = item.optInt("id");
                text(panel, str(item, concerns ? "title" : "type_label"), 18, NAVY, true);
                text(panel, str(item, concerns ? "reference" : "reference_number"), 11, MUTED, false);
                text(panel, str(item, "status_label"), 14, TEAL, true);
                if (!concerns) text(panel, str(item, "payment_status_label"), 12, MUTED, false);
                button(panel, "View details", () -> detail(concerns, id, 1));
            }
            pagination(body, page, result.optInt("last_page", 1), next -> listing(concerns, next));
        });
    }
    private void pagination(LinearLayout panel, int page, int last, Consumer<Integer> next) {
        if (last < 2) return;
        text(panel, "Page " + page + " of " + last, 12, MUTED, false);
        if (page > 1) button(panel, "Previous page", () -> next.accept(page - 1));
        if (page < last) button(panel, "Next page", () -> next.accept(page + 1));
    }
    private void create(boolean concerns) {
        tab = concerns ? "Concerns" : "Requests"; screen(concerns ? "Report a concern" : "Request a document", "Sent directly to your assigned barangay.", true, () -> listing(concerns, 1));
        call("GET", "catalog", null, catalog -> {
            LinearLayout panel = card(); JSONArray options = catalog.optJSONArray(concerns ? "categories" : "document_types");
            if (options == null || options.length() == 0) { text(panel, "No options available. Contact your barangay.", 15, MUTED, false); return; }
            if (!concerns) for (int i=0;i<options.length();i++) {
                JSONObject option = options.optJSONObject(i);
                try { option.put("display", option.optString("label") + " — PHP " + option.optDouble("fee") + (option.optBoolean("available") ? "" : " (unavailable)")); } catch (JSONException ignored) {}
            }
            Spinner choice = choices(panel, concerns ? "Category" : "Document type", options, concerns ? "label" : "display");
            EditText title = concerns ? field(panel, "Concern title", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_SENTENCES) : null;
            EditText description = field(panel, concerns ? "Description (20–4000 characters)" : "Purpose (up to 255 characters)", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
            description.setMinLines(3);
            EditText location = concerns ? field(panel, "Location (optional)", InputType.TYPE_CLASS_TEXT) : null;
            Uri[] attachment = new Uri[1];
            if (concerns) photoPicker(panel, attachment, "Attach photo (optional, JPG/PNG, max 5 MB)");
            button(panel, concerns ? "Submit concern" : "Submit document request", () -> {
                JSONObject option = options.optJSONObject(choice.getSelectedItemPosition());
                if (!concerns && !option.optBoolean("available")) { alert("Document unavailable", "Online payment is not active for this document. Contact your barangay."); return; }
                JSONObject data = concerns ? json("category", option.optString("key"), "title", value(title), "description", value(description), "location", value(location))
                    : json("document_type", option.optString("key"), "purpose", value(description));
                send(concerns ? "concerns" : "documents", data, attachment[0], "attachment", result -> detail(concerns, result.optInt("id"), 1));
            });
        });
    }
    private void detail(boolean concerns, int id, int page) {
        tab = concerns ? "Concerns" : "Requests"; screen(concerns ? "Concern details" : "Request details", "Status and messages from your barangay.", true, () -> listing(concerns, 1));
        call("GET", (concerns ? "concerns/" : "documents/") + id + "?page=" + page, null, result -> {
            JSONObject data = result.optJSONObject("data"); LinearLayout panel = card();
            text(panel, str(data, concerns ? "title" : "type_label"), 21, NAVY, true);
            text(panel, str(data, concerns ? "reference" : "reference_number"), 12, MUTED, false);
            text(panel, str(data, "status_label"), 16, TEAL, true);
            text(panel, str(data, concerns ? "description" : "purpose"), 15, NAVY, false);
            if (concerns) {
                text(panel, str(data, "location"), 13, MUTED, false);
                JSONObject history = result.optJSONObject("updates"); JSONArray updates = history == null ? null : history.optJSONArray("data");
                text(panel, "Activity & updates", 18, NAVY, true);
                if (updates != null) for (int i=0;i<updates.length();i++) {
                    JSONObject update = updates.optJSONObject(i); text(panel, str(update, "created_at"), 11, MUTED, false); text(panel, str(update, "message"), 14, NAVY, false);
                }
                if (history != null) pagination(panel, page, history.optInt("last_page", 1), next -> detail(true, id, next));
                if (!"closed".equals(str(data, "status"))) {
                    EditText message = field(panel, "Add information (5–2000 characters)", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE);
                    button(panel, "Send information", () -> call("POST", "concerns/" + id + "/reply", json("message", value(message)), response -> detail(true, id, 1)));
                }
            } else {
                text(panel, "Amount due: PHP " + str(data, "amount_due"), 17, NAVY, true);
                text(panel, str(data, "payment_status_label"), 14, TEAL, true);
                text(panel, str(data, "remarks"), 14, NAVY, false); text(panel, str(data, "payment_remarks"), 14, MUTED, false);
                text(panel, str(data, "payment_reference").isEmpty() ? "" : "GCash reference: " + str(data, "payment_reference"), 12, MUTED, false);
                String status = str(data, "payment_status"); JSONObject payment = result.optJSONObject("payment");
                if (data.optDouble("amount_due") > 0 && ("unpaid".equals(status) || "rejected".equals(status))) {
                    if (payment == null) text(panel, "GCash collection is unavailable. Contact your barangay.", 14, MUTED, false);
                    else {
                        text(panel, "Pay only the official barangay account", 16, NAVY, true);
                        text(panel, str(payment, "merchant") + "\n" + str(payment, "account"), 15, NAVY, false);
                        button(panel, "View official GCash QR", this::showQr);
                        text(panel, "Pay in GCash, then submit your receipt here for staff verification.", 13, MUTED, false);
                        button(panel, "Submit payment receipt", () -> paymentForm(id));
                    }
                }
            }
            button(body, "Refresh status", () -> detail(concerns, id, 1));
        });
    }
    private void showQr() {
        String base = server, credential = token;
        work(() -> ResidentApi.exchange(base, credential, "GET", "payment-qr", null, null, null, null), bytes -> {
            ImageView image = new ImageView(this); image.setAdjustViewBounds(true); image.setPadding(dp(20), dp(20), dp(20), dp(20));
            BitmapFactory.Options bounds = new BitmapFactory.Options(); bounds.inJustDecodeBounds = true;
            BitmapFactory.decodeByteArray(bytes, 0, bytes.length, bounds);
            bounds.inSampleSize = 1;
            while (bounds.outWidth / bounds.inSampleSize > 2048 || bounds.outHeight / bounds.inSampleSize > 2048) bounds.inSampleSize *= 2;
            bounds.inJustDecodeBounds = false;
            image.setImageBitmap(BitmapFactory.decodeByteArray(bytes, 0, bytes.length, bounds)); image.setContentDescription("Official barangay GCash payment QR");
            new AlertDialog.Builder(this).setTitle("Official GCash QR").setView(image).setPositiveButton("Close", null).show();
        }, false);
    }
    private void photoPicker(LinearLayout panel, Uri[] selected, String label) {
        TextView filename = text(panel, "No photo selected", 12, MUTED, false);
        button(panel, label, () -> { fileReceiver = uri -> { selected[0] = uri; filename.setText("Photo selected"); };
            Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT).setType("image/*").addCategory(Intent.CATEGORY_OPENABLE);
            intent.putExtra(Intent.EXTRA_MIME_TYPES, new String[]{"image/jpeg", "image/png"}); startActivityForResult(intent, 42);
        });
    }
    @Override protected void onActivityResult(int request, int result, Intent data) {
        super.onActivityResult(request, result, data);
        if (request == 42 && result == RESULT_OK && data != null && data.getData() != null && fileReceiver != null) fileReceiver.accept(data.getData());
        fileReceiver = null;
    }
    private void send(String path, JSONObject data, Uri file, String field, Consumer<JSONObject> success) {
        if (file == null) { call("POST", path, data, success); return; }
        String base = server, credential = token;
        work(() -> {
            String mime = getContentResolver().getType(file);
            if (!"image/jpeg".equals(mime) && !"image/png".equals(mime)) throw new ResidentApi.Failure(422, "Choose a JPG or PNG image.");
            byte[] bytes; try (InputStream input = getContentResolver().openInputStream(file)) { bytes = ResidentApi.read(input, 5 * 1024 * 1024); }
            return ResidentApi.request(base, credential, "POST", path, data, bytes, field, mime);
        }, success, true);
    }
    private void paymentForm(int id) {
        screen("Submit payment receipt", "This submits proof; it does not charge your account.", true, () -> detail(false, id, 1));
        LinearLayout panel = card(); EditText name = field(panel, "Payer name", InputType.TYPE_CLASS_TEXT); name.setText(str(user, "name"));
        EditText mobile = field(panel, "GCash mobile number (09xxxxxxxxx)", InputType.TYPE_CLASS_PHONE);
        EditText reference = field(panel, "13-digit GCash reference", InputType.TYPE_CLASS_NUMBER);
        EditText date = field(panel, "Payment date and time (YYYY-MM-DD HH:MM, Philippine time)", InputType.TYPE_CLASS_DATETIME);
        date.setFocusable(false);
        date.setOnClickListener(v -> {
            java.util.Calendar clock = java.util.Calendar.getInstance(java.util.TimeZone.getTimeZone("Asia/Manila"));
            new android.app.DatePickerDialog(this, (picker, year, month, day) -> {
                new android.app.TimePickerDialog(this, (timePicker, hour, minute) -> date.setText(String.format(java.util.Locale.US,
                    "%04d-%02d-%02d %02d:%02d", year, month + 1, day, hour, minute)), clock.get(java.util.Calendar.HOUR_OF_DAY), clock.get(java.util.Calendar.MINUTE), true).show();
            }, clock.get(java.util.Calendar.YEAR), clock.get(java.util.Calendar.MONTH), clock.get(java.util.Calendar.DAY_OF_MONTH)).show();
        });
        Uri[] receipt = new Uri[1]; photoPicker(panel, receipt, "Choose receipt (JPG/PNG, max 5 MB)");
        button(panel, "Submit for verification", () -> {
            if (receipt[0] == null) { alert("Receipt required", "Choose a photo of your GCash receipt."); return; }
            send("documents/" + id + "/payment", json("payer_name", value(name), "payer_mobile", value(mobile), "payment_reference", value(reference),
                "payment_transaction_at", value(date).trim() + "+08:00"), receipt[0], "payment_proof", result -> detail(false, id, 1));
        });
    }
    private void profile() {
        tab = "Profile"; screen("My profile", "Your resident account and security.", true, this::home);
        call("GET", "me", null, result -> {
            user = result.optJSONObject("user"); LinearLayout panel = card();
            text(panel, str(user, "name"), 22, NAVY, true); text(panel, "Barangay " + str(user.optJSONObject("barangay"), "name"), 14, TEAL, true);
            text(panel, "To correct your verified name or barangay, contact your barangay office.", 13, MUTED, false);
            EditText email = field(panel, "Email address", InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_EMAIL_ADDRESS); email.setText(str(user, "email"));
            EditText current = password(panel, "Current password"), next = password(panel, "New password (optional)"), confirm = password(panel, "Confirm new password");
            button(panel, "Save account changes", () -> call("PUT", "profile", json("email", value(email), "current_password", value(current), "password", value(next), "password_confirmation", value(confirm)), response -> {
                clearSession(); login(); alert("Account updated", "Sign in again using your updated details.");
            }));
            button(body, "Sign out", this::logout);
        });
    }
    private void logout() {
        String base = server, credential = token;
        work(() -> {
            try { ResidentApi.request(base, credential, "POST", "logout", json(), null, null, null); return true; }
            catch (Exception error) { return false; }
        }, revoked -> {
            clearSession(); login();
            if (!revoked) alert("Signed out on this device", "Server sign-out could not be confirmed. The saved sign-in was removed from this phone.");
        }, false);
    }
    @Override public void onBackPressed() { if (!busy) { if (backAction != null) backAction.run(); else super.onBackPressed(); } }
    @Override protected void onDestroy() { screenId++; executor.shutdownNow(); super.onDestroy(); }
}
