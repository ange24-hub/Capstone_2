package ph.tomasoppus.rbim.resident;

import org.junit.Test;
import static org.junit.Assert.*;

public class ResidentApiTest {
    @Test public void allowsPrivateLanAndEmulatorInDebug() throws Exception {
        assertEquals("http://192.168.1.10:8000", ResidentApi.server(" http://192.168.1.10:8000/ "));
        assertEquals("http://10.0.2.2:8000", ResidentApi.server("http://10.0.2.2:8000"));
        assertEquals("https://example.test/rbim", ResidentApi.server("https://example.test/rbim/"));
    }
    @Test public void rejectsRemoteCleartextCredentialsQueriesAndFragments() {
        for (String input : new String[]{"http://example.com", "http://172.32.0.1", "https://user:pass@example.com", "https://example.com?token=x", "https://example.com#fragment", "file:///etc", "not-a-url"}) {
            assertThrows(input, Exception.class, () -> ResidentApi.server(input));
        }
    }
    @Test public void boundsResponseAndUploadReads() throws Exception {
        assertArrayEquals(new byte[]{1,2}, ResidentApi.read(new java.io.ByteArrayInputStream(new byte[]{1,2}), 2));
        assertThrows(java.io.IOException.class, () -> ResidentApi.read(new java.io.ByteArrayInputStream(new byte[]{1,2,3}), 2));
    }
}
