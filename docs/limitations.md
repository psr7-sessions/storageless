## StorageLessSession Limitations

StorageLessSession has a few limitations derived from its design.

The idea around StorageLessSession is that a session is not supposed to be
an actual storage for transient client information, but rather be used for
the concerns of authentication, authorization and eventually and for validation
concerns such as CSRF-token validation.

If you want to store frequently-updated or concurrently-updated information
inside a session, then StorageLessSession is likely not fitting your use-case.

#### Sessions cannot be invalidated