import { NextResponse, type NextRequest } from "next/server";

/**
 * UX guard only (decision 7B): checks whether the "signed in" marker cookie exists.
 * It never reads or verifies a token — FastAPI authorises every API request and
 * the internal layout re-confirms the account via GET /api/auth/me.
 */
const SESSION_COOKIE = "soesis_session";

export function proxy(request: NextRequest) {
  const signedIn = request.cookies.has(SESSION_COOKIE);
  const { pathname, search } = request.nextUrl;

  if (pathname === "/login") {
    return signedIn ? NextResponse.redirect(new URL("/status", request.url)) : NextResponse.next();
  }

  if (!signedIn) {
    const login = new URL("/login", request.url);
    login.searchParams.set("next", pathname + search);
    return NextResponse.redirect(login);
  }

  return NextResponse.next();
}

// Internal pages and the sign-in page only; the public area and /api/* are never touched.
export const config = {
  matcher: ["/login", "/status", "/admin/:path*"],
};
