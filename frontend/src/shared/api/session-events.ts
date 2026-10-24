/** Fired when a request is still 401 after a refresh attempt: the sign-in session has ended. */

type Listener = () => void;

const listeners = new Set<Listener>();

export function onSessionExpired(listener: Listener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function notifySessionExpired(): void {
  listeners.forEach((listener) => listener());
}
