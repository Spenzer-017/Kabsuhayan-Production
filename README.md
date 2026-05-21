<!-- Developer's Notes (For Myself & Maybe My Groupmates) -->

# Website Design
Color Palette:
#005F02
#427A43
#C0B87A
#F2E3BB

# Header (header.php)
In navigation link the code <?= ($activePage ?? '') === 'home' ? 'class="active"' : '' ?> just
uses a null coalescing and ternary operators, null coalescing operator is kinda like the isset()
method so if $activePage exist and is not null use it else use the default. So in this code
if the activepage is home then set its class to active else dont put anything. Oh, and also
<?= ?> is just the shorthand echo tag. There's also the hamburger menu that is responsible for
the UI for mobile users, it works by having a toggle basically, when the menu-toggle is clicked 
and nav has no class, it adds the class="open" to the nav, else if it did have class="open" then
it removes it.

_Header-CSS_
nothing really important here besides the hamburger menu and the mobile responsiveness. It
detects the width of the device if its 768px and below it activates the hamburger menu setting
the menu-toggle display to block and making the nav flex-direction to be column so that its
easier to see in mobile and other devices.

# Footer (footer.php)
Nothing important... The script below just makes a hamburger menu toggle for mobile devices.
It works by adding a class="open" in nav if it doesn't have it, and removes it if it does
have it, then CSS does it job.

_Footer-CSS_
Not much important information here either. 4 divs for 4 sections, unordered list flex-direction
are column, h5 has bolder text-weight, and anchor tags have no decoration and has hover effects.
Theres also the copyright mention div at the very bottom that displays real-time year.

# Theme Switch
NOTE: The theme switch button only works if you include both the header and the footer because
the script for the them switch button is in the footer. It works by listening to a click and
adding the class="darkmode" to the body if its not active and its null, else if its active and
has class="darkmode" it removes it. It also communicates and saves the states to the server
local storage so that it saves the state even if you quit the website.

_Theme-Switch-CSS_
root variables are declared so that changing it to darkmode reflects to all other CSS properties
that has the root variables as values. At lightmode the img:last-child display is set to none, so 
that the darkmode icon is the one that is on display and when its in darkmode the icon's switches.
Also the footer is not affected by the theme because, i don't like how it looks when it changes.
There's still room for improvement on the color scheme of my themes like in hover and buttons.

# Things To Take Note Of
(Optional):
- Image upload might need to change where it saves
- The load page specific javascript in the footer have no purpose for now because almost all script are embedded
- Currently only accepting int in selling prices, maybe make it accept decimal/float prices
- Things to maybe implement (account deletion option)
- Viewing of other user's profile
(Less Important):
(Important):
- Pages to check QA: (saved-items, my-listings, edit-listings, messages, listing, dashboard, transaction, reactions, react)
- Navbar design overhaul, specifically towards profile
- Things to fix:
- Things to look out for:
    * front-end issues like message to long overflowing, etc. check for all pages
    * responsiveness for all pages

# Known Minor Issues/Limitations
- Some features are optimized primarily for desktop view, minor layout inconsistencies may appear on smaller mobile screens
- Edge-case input formatting (very long words or unusual characters) may slightly break layout wrapping 
- Some success/error messages may persist until the page is manually refreshed or navigated away
- Very fast navigation between pages may temporarily show outdated cached data, and theme might flicker
- Some database-heavy pages may experience slight delay when loading large datasets
- Notifications may not always appear instantly depending on page state or refresh timing
- Search and filter results may require a refresh to fully reflect the latest database changes in rare cases

<!-- Weekly Report -->

> Week #1
- Made includes like header and footer for reusability across the website
- Made sure the header and footer are responsive depending on the device
- Made a simple UI with a CvSU themed color palette (could be improve later on)
- Implemented a navbar that changes between logged in users and guests
- Implemented a Dark mode feature for me to not burn my eyes

> Week #2
- Made a incomplete front-end for main pages for my website (improve/change later on)
- Also made sure that it is responsive depending on the device
- Createda a mock database to help visualize the front-end pages (replace with a real mysql database later)

> Week #3
- Made everything functional by connecting most pages with the created database
- Made some improvements to the front-end of the page
- Made authentication (Login, Signup, Logout)
- Made a front-end for authentication pages (Login, Signup)
- Made a front-end for other pages (Transaction, Messages, Listing, My Listings, Edit Listing, Saved Items, Info)
- Made a include dedicated for database connection/access and also an include for deleting items and all it's shared data
- Remove manual forced logins and set the $_SESSION to $user in the database
- Added protection to protected pages like (dashboard, profile, etc.) redirecting guest into login page if they aren't logged in

> Week #4
- Added confirmation modals for important actions (made the footer handle the JS script for the modals)
- Implemented a infinite scroll feature on the browse page that dynamically fetches items using an API
- Integrated Gmail SMTP using PHPMailer to send contact form submissions to kabsuhayan@gmail.com
- Added terms & conditions agreement before account registration
- Implemented an email verification system that sends OTP codes through SMTP using PHPMailer
- Added a developer mode for easier OTP testing during development
- Made a dynamically changing page titles for better UX

> Week #5
- Redesigned the listing page layout to a Facebook Marketplace inspired two-column structure
- Implemented a Junimo-themed reaction system with 5 custom pixel-art emojis (Like, Heart, Laugh, Wow, Cry)
- Built a REST API endpoint with AJAX handling for adding, changing, and removing reactions per listing with live count updates
- Implemented a full comment and reply system with inline editing, soft delete, seller moderation, and reply threading
- Added a notification system with a header bell icon, unread badge, dropdown preview, and a dedicated notifications page
- Notifications are triggered by reactions, comments, replies, messages, and transaction status changes
- Refactored listing page CSS to use namespaced `lp-` prefixed classes to prevent style collisions with other pages
- Implemented a forgot password system with email OTP verification using PHPMailer and SMTP