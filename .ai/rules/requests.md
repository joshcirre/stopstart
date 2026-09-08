---
paths:
  - 'app/Http/Requests/**'
---

# Requests

## Owner check runs in authorize(), and postJson sends no cookies
Project-scoped FormRequests use the AuthorizesProjectOwner trait so ownership is checked before validation. With the check only in the controller, a non-owner's malformed request failed validation first and answered 422 — telling them the project id exists, since a missing id 404s at route binding. The trait aborts 404 in both cases. It skips the check when the route binds {project:remote_token}: StoreRenderedVideoRequest is shared by VideoController@upload (owner cookie) and DubController@export (paired phone, no owner cookie, authorized by the token in the URL). Test gotcha that hid this: postJson() sends NO cookies unless you chain withCredentials(), so any postJson test asserting 422 was never authenticated and passed only because validation preceded authorization.
