export {};

declare global {
  interface Window {
    APP: {
      isDev: boolean;
      SITE_TITLE: string;
      BASE_URL: string;
      HOME_URL: string;
      AUTHENTICATED: boolean;
    };
  }
}
