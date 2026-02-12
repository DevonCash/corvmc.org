<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Seeder;

class ProgramsPageSeeder extends Seeder
{
    public function run(): void
    {
        SitePage::updateOrCreate(
            ['slug' => 'programs'],
            [
                'title' => 'Programs',
                'content' => $this->content(),
            ]
        );
    }

    private function content(): string
    {
        return <<<'MD'
::::section[Programs]{columns=1 fullBleed=true description="Practice spaces, performances, meetups & clubs for the music community"}
::::

::::section[Practice Space]{.bg-success/10 .px-8 .py-12 columns=2 description="Professional rehearsal rooms available to CMC members, equipped with the gear you need to develop your craft."}

### Affordable Practice Space for Musicians

Our practice rooms are equipped with professional gear and designed for musicians who need a reliable space to rehearse, record demos, and develop their craft.

Members can book hourly sessions in our sound treated practice room, complete with a PA system, microphones, and all the essentials for a productive practice session.

:::note
Practice space access requires a free CMC membership
:::

---

:::card[Room Features]{icon=tabler-music color=success}
- Sound treated walls
- Full PA system
- Microphones & stands
- Drum kit (cymbals & hardware)
- Guitar & bass amplifiers
- Comfortable seating
:::
::::

::::section{.bg-success/10 .px-8 .py-12 columns=2}
::stat[Standard Rate]{value="$15/hour" subtitle="All equipment included" color=base}
---
::stat[Sustaining Members]{value="up to 10 Free Hours" subtitle="then $15/hour" color=primary}
::::

::::section[Shows & Performances]{.bg-primary/20 .px-8 .py-12 columns=2 description="Showcase your talent and connect with the community through our regular performance opportunities and special events."}

:::card[Performance Opportunities]{icon=tabler-microphone-2 color=primary}
- Monthly showcase events
- Open mic nights
- Collaborative performances
- Community festivals
- Recording showcases
:::

---

### Perform & Connect

Whether you're a seasoned performer or just starting out, our performance programs provide supportive environments to share your music with appreciative audiences.

From intimate acoustic sets to full band productions, we create spaces where musicians can grow, collaborate, and celebrate the power of live music.
::::

::::section{.bg-primary/20 .px-8 .py-12 columns=2}
::button[View Upcoming Shows]{url=/events color=primary}
---
::button[Apply to Perform]{url="/contact?topic=performance" color=secondary variant=outline}
::::

::::section[Meetups & Clubs]{.bg-warning/20 .px-8 .py-12 columns=2 description="Connect with like-minded musicians through our regular meetups, learning sessions, and specialty clubs."}

:::card[Real Book Club]{icon=tabler-music color=warning}
Our flagship jazz jam club where musicians of all levels come together to explore the Great American Songbook and beyond.

| | |
|---|---|
| **When** | 1st Thursday of every month, 6:30 PM – 8:00 PM |
| **Format** | Open jam session, all skill levels welcome |

### What We Do

- Work through Real Book standards
- Practice improvisation in a supportive environment
- Connect with other jazz enthusiasts

> [!TIP]
> Bring your instrument and a Real Book (or we'll share!)
:::

---

:::card[Songwriter Circle]{icon=tabler-users color=base}
Monthly gathering for sharing original songs, getting feedback, and collaborating on new material.

**2nd Saturday · 2:00 PM**
:::

---

:::card[Monthly Meetup]{icon=tabler-microphone color=base}
Come chat with — or just listen to — other local musicians about gear, gigs, and everything music-related. Everyone is welcome!

**Last Thursday · 6:30 PM**
:::
::::

::::section{.bg-warning/20 .px-8 .py-12 columns=2}
::button[Join a Meetup]{url="/contact?topic=general" color=primary}
---
::button[Connect with Members]{url="/directory?tab=musicians" color=secondary variant=outline}
::::

::::section[Gear Lending Library]{.bg-info/20 .px-8 .py-12 columns=2 description="Access professional music equipment through our member gear lending program. Try before you buy, or use quality gear for your performances and recordings."}

### Quality Gear When You Need It

Our lending library features carefully maintained instruments and equipment available to CMC members for short-term use.

Perfect for trying new instruments, covering for repairs, or accessing specialized gear for recording projects and performances.

:::note
Gear lending requires CMC membership and good standing
:::

---

:::card[Available Equipment]{icon=tabler-guitar-pick color=info}
- Electric guitars & basses
- Acoustic instruments
- Amplifiers & effects pedals
- Recording equipment
- Percussion instruments
- Specialty instruments
:::
::::

::::section{.bg-info/20 .px-8 .py-12 columns=3}
::stat[Borrowing Period]{value="1-2 Weeks" subtitle="Renewable based on availability" color=base}
---
::stat[Security Deposit]{value="Varies" subtitle="Refundable upon return" color=base}
---
::stat[Rental Fee]{value="Low Cost" subtitle="Covers maintenance & replacement" color=base}
::::

::::section{.bg-info/20 .px-8 .py-12 columns=2}
::button[Browse Available Gear]{url="/contact?topic=gear" color=info}
---
::button[Donate Equipment]{url="/contact?topic=gear" color=info variant=outline}
::::

::::section[Ready to Get Involved?]{.bg-primary/20 .px-8 .py-12 columns=3 description="Join the Corvallis Music Collective to access all our programs and connect with a vibrant community of musicians."}
::step[Join CMC]{icon=tabler-number-1 description="Become a member to access all programs"}
---
::step[Choose Your Path]{icon=tabler-number-2 description="Practice, perform, or join our clubs"}
---
::step[Make Music]{icon=tabler-number-3 description="Connect and create with the community"}
::::

::::section{.bg-primary/20 .px-8 .py-12 columns=2}
::button[Become a Member]{url=/member/register color=primary}
---
::button[Ask Questions]{url="/contact?topic=general" color=secondary variant=outline}
::::
MD;
    }
}
