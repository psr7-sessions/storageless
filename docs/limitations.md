## StorageLessSession Limitations

StorageLessSession has a few limitations derived from its design.

#### Cannot store private information in the session

StorageLessSession stores session data in cookies in an unencrypted JWT token.
The fact that the token is unencrypted means that all the information in the
session is also available in read-only mode to the user agent.

Storing information such as the user identifier, the user data, CSRF tokens
and similar is perfectly OK, but storing sensitive information that should
never be shared with the client MUST be avoided.

This is actually also valid for traditional PHP sessions, since those
sessions may be read by various processes.

#### Sessions cannot be invalidated

There is no way to (securely) manually invalidate a session just via
StorageLessSession.

By default, StorageLessSession does not assign any identifier to sessions,
nor identifies sessions at all: it just verifies the session signature to
validate the author of its contents.

If you want to manually lock out a particular user agent that logged in with
a certain session cookie, then you will have to design a mechanism for that.
You will need to identify (and attach identifiers to) session cookies, and
then manually block clients with those identifiers in the session cookie.
Note that this approach also defeats the benefits of StorageLessSession,
therefore you may want to just use traditional sessions.

This limitation is also why StorageLessSession should only be used with secure
(TLS) HTTPS connections: if any session is spoofed, there is no way to lock
out an attacker.

#### Increased network traffic

This is a very minor detail, but you may have increased network transfer
due to the session cookie being quite large, and being part of headers sent
from the user agent in every HTTP request.

#### Race conditions with highly concurrent HTTP requests writing to session

Since StorageLessSession uses the [`SetCookie`](https://en.wikipedia.org/wiki/HTTP_cookie#Setting_a_cookie)
header to write data to the user-agent, exploiting the user-agent as a storage,
it is not safe to use it for highly concurrent write operations.

The idea around StorageLessSession is that a session is not supposed to be
an actual storage for transient client information, but rather be used for
the concerns of authentication, authorization and eventually and for validation
concerns such as CSRF-token validation.

If you want to store frequently-updated or concurrently-updated information
inside a session, then StorageLessSession is likely not fitting your use-case.
