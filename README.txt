block_viewasexample


Note, this code comes with ABSOLUTLY NO SUPPORT. We made it for our interenal
use at the OU, and are only sharing it because people expressed an interest.
(http://moodle.org/mod/forum/discuss.php?d=190087). Only use this if you have
reviewed it carefully yourself, and are prepared to fix any bugs you encounter.


This block provides an enahnced 'switch role' functionality. If you add the
block to a course, then it shows like 'View as example student' and/or
'View as example tutor' to those users who have the appropriate capability.

Clicking one of those links will:
1. Find or create an appropriate dummy user account.
2. Enrol that user in the course if necessary.
3. Add the user to some groups - the is rather OU-specific and may not generally be useful.
4. Switch the current user to the dummy user account, using Moodle's login-as feature.

Users get back to their normal role in the same way that they would using the
full login-as feature.

Note that this block does not require users to have the full login-as
capability, therefore, there should not be any privacy issues with this.

As you look at the code, note that Tutor is the OU equivalent of non-editing teacher.


Credit goes to sam marshall for the design, and Alan Thompson for the code.
