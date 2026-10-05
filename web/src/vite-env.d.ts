/// <reference types="vite/client" />

interface ImportMetaEnv {
  /**
   * Origin of the Laravel API in production, e.g. https://api.takda.ph
   *
   * Unset in development, where the Vite dev proxy serves /api from :8000.
   * Set at build time on the static host. The API must separately allow this
   * origin via TAKDA_FRONTEND_URL for CORS.
   */
  readonly VITE_API_BASE_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
