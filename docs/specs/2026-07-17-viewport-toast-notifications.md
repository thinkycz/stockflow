# Viewport Toast Notifications

Global Inertia `flash.success` and `flash.error` messages must remain visible
after form submissions even when the page is scrolled. Success messages use a
fixed, dismissible toast that closes after five seconds; errors remain until
dismissed. Field-level validation stays inline.

The backend flash contract is unchanged. Authenticated pages offset the mobile
toast below the app header, while guest pages use the normal viewport inset.
Success uses polite status semantics and errors use alert semantics. Both close
buttons have localized accessible labels in English, Czech, and Slovak.
