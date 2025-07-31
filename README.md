# Corvallis Music Collective

This is a site for the Corvallis Music Collective, a nonprofit organization operating out of Corvallis, Oregon.

## Critical Features

Features are listed in order of priority. Each should be implemented in an isolated module using as many pre-existing tools as possible. Moderation and access to each feature should be handled on an individual basis to allow volunteers to take on a subset of features for moderation.

### Membership (TODO)

Phase 1: Allow users to sign up to the website.

Phase 2: All users who sign up to the site are considered members. Users with a monthly recurring donation exceeding $10 are "sustaining members". Staff are employees or volunteers for the music collective who have access to the moderation tools for each of the features.

Payments are processed via Zeffy – a payment processor free for non-profits. Zeffy has no direct export features, but they have a Zapier integration for notifying a server when a payment is recieved – that will have to be enough!

### Productions (TODO)

Phase 1: Display upcoming events on the site, automatically redirect from /show-tonight to the next show being held at the collective.

Phase 2: Give staff a centralized area for managing shows at the Collective's building. Should include a consistent workflow and task assignment.

### Practice Space Scheduling (TODO)

Phase 1: Allow members to schedule practice space at our building. Practice space is available at a rate of $15 dollars per hour, and sustiaining members get 4 free hours per month.

Phase 2: Sustaining members can create recurring reservations at the space. Needs confirmation workflow & waitlisting.

### Member Directory (TODO)

Phase 1: Give users a place to list their skills and abilities.

Phase 2: Sustaining members can list their profiles publicly, including on our lists of teachers and other professionals.

### Band Directory (TODO)

Phase 1: Allow users to create and manage band profiles. Allow bands to post links to their social media pages, along with an avatar. Add a "web ring" which will allow users to browse through a linked list of bands.

Phase 2: Sustaining members can post their EPK, including file hosting etc, and add events to their band page.

## Planned Features

Planned features are not ordered by priority

### Community Calendar

Allow members to submit their own events to be displayed on a public calendar. Provide notifications and pubsub feeds. Allow following based on tags, etc.

### Sponsorship management

Tools for sponsors to manage their contribution and rewards and control the branding we use on co-branded material

### User Groups

Member club management tools, emphasis on in-person meetups over online discussion. Include features to support band formation, etc.

### Flyering Tools and introspection

Use unique flyer qr codes to track performance of poster locations, allow for a/b testing, link substitution, etc. Additionally, sustaining members can have their posters hung by our street team.

### Equipment Management Tools

Phase 1: Track make, model, and maintenance on equipment the collective is responsible for, and keep track of who owns it (if it's leant) or who donated it

Phase 2: Allow for reservation and lending of managed equipment, scheduling around maintenance and prioritizing in-house use.

### Equipment Exchange

Allow members to post equipment for local purchase/exchange.

### Custom Theming

We want to support a wide variety of musicians on the site, so it would be nice to allow a degree of customization for member and band profiles. This could just be custom colors, but it would be fun to allow the custom css style customization that myspace granted access to.

### Music hosting and streaming

Allow bands to upload their own music to be played on the website and opt-in to be used at CMC events and in our business-music package.

### Volunteer hour tracking

We can set up a threshold for unlocking sustaining membership with volunteer hours
