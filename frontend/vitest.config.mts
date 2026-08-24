import { defineConfig } from 'vitest/config';

/**
 * Minimal setup: only `vitest` + `jsdom` (for a real `window.localStorage`
 * in the storage tests). No @testing-library/react or @vitejs/plugin-react
 * — this project has no component-render tests yet, only pure-function
 * tests (cart reducer, cart storage), so the React/JSX toolchain those
 * packages provide isn't needed.
 */
export default defineConfig({
  test: {
    environment: 'jsdom',
    // lib/api/client.ts reads NEXT_PUBLIC_API_URL into a module-level
    // const at import time — setting it in a test's beforeEach() has no
    // effect on that already-evaluated value. Vitest applies `env` before
    // any test file is imported, so this is the one place that reliably
    // works. Value is arbitrary and never actually contacted: every fetch
    // in lib/api/*.test.ts is mocked.
    env: {
      NEXT_PUBLIC_API_URL: 'http://127.0.0.1:8000/api/v1',
    },
  },
  resolve: {
    alias: {
      '@': import.meta.dirname,
    },
  },
});
