package ph.tomasoppus.rbim.resident;

import org.json.JSONObject;
import org.json.JSONArray;
import java.net.HttpURLConnection;
import java.net.URI;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.util.Iterator;

final class ResidentApi {
    static class Failure extends Exception {
        final int status;
        Failure(int status, String message) { super(message); this.status = status; }
    }
    static String server(String value) throws Exception {
        String base = value.trim().replaceAll("/+$", "");
        URI uri = new URI(base);
        String host = uri.getHost();
        if (host == null || uri.getRawUserInfo() != null || uri.getRawQuery() != null || uri.getRawFragment() != null) throw new Exception("Enter a server URL, for example http://192.168.1.10:8000");
        boolean local = host.equals("localhost") || host.matches("127\\.\\d+\\.\\d+\\.\\d+") || host.matches("10\\.\\d+\\.\\d+\\.\\d+")
            || host.matches("192\\.168\\.\\d+\\.\\d+") || host.matches("172\\.(1[6-9]|2[0-9]|3[01])\\.\\d+\\.\\d+");
        if (!"https".equals(uri.getScheme()) && !(BuildConfig.DEBUG && local && "http".equals(uri.getScheme()))) throw new Exception("Use HTTPS. Local HTTP is available only for private-network testing.");
        return base;
    }
    static JSONObject request(String base, String token, String method, String path, JSONObject data, byte[] file, String fileKey, String mime) throws Exception {
        byte[] response = exchange(base, token, method, path, data, file, fileKey, mime);
        return new JSONObject(new String(response, StandardCharsets.UTF_8));
    }
    static byte[] exchange(String base, String token, String method, String path, JSONObject data, byte[] file, String fileKey, String mime) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) URI.create(server(base) + "/api/resident/v1/" + path).toURL().openConnection();
        try {
            connection.setRequestMethod(method); connection.setConnectTimeout(15000); connection.setReadTimeout(25000);
            connection.setInstanceFollowRedirects(false); connection.setUseCaches(false);
            connection.setRequestProperty("Accept", "application/json");
            if (!token.isEmpty()) connection.setRequestProperty("Authorization", "Bearer " + token);
            if (data != null) {
                connection.setDoOutput(true);
                String boundary = "RBIM" + java.util.UUID.randomUUID();
                connection.setRequestProperty("Content-Type", file == null ? "application/json; charset=utf-8" : "multipart/form-data; boundary=" + boundary);
                try (OutputStream out = connection.getOutputStream()) {
                    if (file == null) out.write(data.toString().getBytes(StandardCharsets.UTF_8));
                    else {
                        for (Iterator<String> keys = data.keys(); keys.hasNext();) {
                            String key = keys.next();
                            out.write(("--" + boundary + "\r\nContent-Disposition: form-data; name=\"" + key + "\"\r\n\r\n" + data.optString(key) + "\r\n").getBytes(StandardCharsets.UTF_8));
                        }
                        String extension = "image/png".equals(mime) ? "png" : "jpg";
                        out.write(("--" + boundary + "\r\nContent-Disposition: form-data; name=\"" + fileKey + "\"; filename=\"attachment." + extension + "\"\r\nContent-Type: " + mime + "\r\n\r\n").getBytes(StandardCharsets.UTF_8));
                        out.write(file); out.write(("\r\n--" + boundary + "--\r\n").getBytes(StandardCharsets.UTF_8));
                    }
                }
            }
            int status = connection.getResponseCode();
            byte[] bytes;
            try (InputStream stream = status >= 400 ? connection.getErrorStream() : connection.getInputStream()) { bytes = read(stream, 6 * 1024 * 1024); }
            if (status < 200 || status >= 300) {
                String message = status == 429 ? "Too many attempts. Please wait a minute." : status >= 500 ? "The server is unavailable. Please try again later." : "Request failed (" + status + ").";
                if (status < 500) try {
                    JSONObject error = new JSONObject(new String(bytes, StandardCharsets.UTF_8));
                    message = error.optString("message", message);
                    JSONObject errors = error.optJSONObject("errors");
                    if (errors != null) {
                        StringBuilder lines = new StringBuilder();
                        for (Iterator<String> keys = errors.keys(); keys.hasNext();) { JSONArray values = errors.optJSONArray(keys.next()); if (values != null) lines.append(values.optString(0)).append("\n"); }
                        if (lines.length() > 0) message = lines.toString().trim();
                    }
                } catch (Exception ignored) {}
                throw new Failure(status, message);
            }
            return bytes;
        } finally { connection.disconnect(); }
    }
    static byte[] read(InputStream stream, int max) throws IOException {
        if (stream == null) return new byte[0];
        ByteArrayOutputStream output = new ByteArrayOutputStream(); byte[] buffer = new byte[8192]; int n;
        while ((n = stream.read(buffer)) != -1) { if (output.size() + n > max) throw new IOException("File or response exceeds the size limit."); output.write(buffer, 0, n); }
        return output.toByteArray();
    }
}
