#### To Do:

- Want to remove all un-needed files from pre-v2.0.0.
- Make the plugin and API faster loading.
- Make the markdown displayed no Blesta.

#### Completed versions:

##### Version 2.1.0:

- Updated authentication for the new Ticaga API (Sanctum Bearer tokens); fixed POST requests that were sending no auth and a body/content-type mismatch.
- Corrected all API calls to match the current Ticaga endpoints and cleaned up unreachable/dead code.
- The admin "Save API details" now performs a real connection test instead of always reporting success, and the settings page shows a live Connected / Not connected status.
- New: when a customer registers in Blesta, the plugin now creates and links a Ticaga account automatically.
- New: "Sync existing accounts" button and a background cron task to backfill/link Ticaga accounts for clients that registered before the integration (matched by email, never duplicated).
- Customers are now automatically linked to a Ticaga account when they log in (and, as a fallback, when they enter the support area) — so Ticaga staff can see the customer and their services straight away. The old "Please Sync" wall is gone; linked customers' tickets are correctly attributed and they can use customer-only departments as well as public ones. If linking can't complete (e.g. the API is unavailable), they fall back to opening a guest ticket with a clear notice.
- Fixed: tickets opened by linked customers are now correctly attached to their Ticaga account (was sending the wrong field, creating them as guest tickets).
- A token from any staff member (admin/employee) can now be used — superadmin is no longer required.

##### Version 2.0.0:

- You can now open tickets as a guest (Not logged in).
- Departments with disabled priorities no longer show the dropdown. 
- Friendlier links like `ticaga_support/client_main/open/sales` this replaces `ticaga_support/client_main/SubmitTicket/1`.
- You can now see and edit the details if you ever need to.
- Markdown editor, this shows fine on Ticaga, just not on the Blesta Plugin yet.
