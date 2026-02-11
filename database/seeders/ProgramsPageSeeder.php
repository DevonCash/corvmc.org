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
                'blocks' => $this->blocks(),
            ]
        );
    }

    /**
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private function blocks(): array
    {
        return [
            // Hero
            $this->section('none', 1, true, [
                $this->header('Programs', 'Practice spaces, performances, meetups & clubs for the music community'),
            ]),

            // Practice Space - intro + card
            $this->section('success', 2, false, [
                $this->header('Practice Space', 'Professional rehearsal rooms available to CMC members, equipped with the gear you need to develop your craft.'),
                $this->prose(
                    "### Affordable Practice Space for Musicians\n\nOur practice rooms are equipped with professional gear and designed for musicians who need a reliable space to rehearse, record demos, and develop their craft.\n\nMembers can book hourly sessions in our sound treated practice room, complete with a PA system, microphones, and all the essentials for a productive practice session.",
                    'tabler-user-circle',
                    '**Members Only** — Practice space access requires a free CMC membership',
                    'info',
                ),
                $this->card('tabler-music', 'Room Features', null, [
                    'Sound treated walls',
                    'Full PA system',
                    'Microphones & stands',
                    'Drum kit (cymbals & hardware)',
                    'Guitar & bass amplifiers',
                    'Comfortable seating',
                ], 'success'),
            ]),

            // Practice Space - pricing stats
            $this->section('success', 2, false, [
                $this->stat('Standard Rate', '$15/hour', 'All equipment included', 'base'),
                $this->stat('Sustaining Members', 'up to 10 Free Hours', 'then $15/hour', 'primary'),
            ]),

            // Shows & Performances - intro + card + prose
            $this->section('primary', 2, false, [
                $this->header('Shows & Performances', 'Showcase your talent and connect with the community through our regular performance opportunities and special events.'),
                $this->card('tabler-microphone-2', 'Performance Opportunities', null, [
                    'Monthly showcase events',
                    'Open mic nights',
                    'Collaborative performances',
                    'Community festivals',
                    'Recording showcases',
                ], 'primary'),
                $this->prose("### Perform & Connect\n\nWhether you're a seasoned performer or just starting out, our performance programs provide supportive environments to share your music with appreciative audiences.\n\nFrom intimate acoustic sets to full band productions, we create spaces where musicians can grow, collaborate, and celebrate the power of live music."),
            ]),

            // Shows & Performances - buttons
            $this->section('primary', 2, false, [
                $this->button('View Upcoming Shows', '/events', 'primary'),
                $this->button('Apply to Perform', '/contact?topic=performance', 'outline-secondary'),
            ]),

            // Meetups & Clubs - intro + detailed card + card stack
            $this->section('warning', 2, false, [
                $this->header('Meetups & Clubs', 'Connect with like-minded musicians through our regular meetups, learning sessions, and specialty clubs.'),
                $this->detailedCard(
                    'Real Book Club',
                    'tabler-music',
                    'bg-amber-500',
                    'Our flagship jazz jam club where musicians of all levels come together to explore the Great American Songbook and beyond.',
                    [
                        ['label' => 'When', 'value' => "1st Thursday of every month\n6:30 PM - 8:00 PM"],
                        ['label' => 'Format', 'value' => "Open jam session\nAll skill levels welcome"],
                    ],
                    'What We Do',
                    [
                        'Work through Real Book standards',
                        'Practice improvisation in a supportive environment',
                        'Connect with other jazz enthusiasts',
                    ],
                    'Bring your instrument and a Real Book (or we\'ll share!)',
                ),
                $this->cardStack([
                    [
                        'name' => 'Songwriter Circle',
                        'icon' => 'tabler-users',
                        'icon_color' => 'text-primary',
                        'description' => 'Monthly gathering for sharing original songs, getting feedback, and collaborating on new material.',
                        'badge' => '2nd Saturday • 2:00 PM',
                    ],
                    [
                        'name' => 'Monthly Meetup',
                        'icon' => 'tabler-microphone',
                        'icon_color' => 'text-secondary',
                        'description' => 'Come chat with - or just listen to - other local musicians about gear, gigs, and everything music-related. Everyone is welcome!',
                        'badge' => 'Last Thursday • 6:30 PM',
                    ],
                ]),
            ]),

            // Meetups & Clubs - buttons
            $this->section('warning', 2, false, [
                $this->button('Join a Meetup', '/contact?topic=general', 'primary'),
                $this->button('Connect with Members', '/directory?tab=musicians', 'outline-secondary'),
            ]),

            // Gear Lending Library - intro + card
            $this->section('info', 2, false, [
                $this->header('Gear Lending Library', 'Access professional music equipment through our member gear lending program. Try before you buy, or use quality gear for your performances and recordings.'),
                $this->prose(
                    "### Quality Gear When You Need It\n\nOur lending library features carefully maintained instruments and equipment available to CMC members for short-term use.\n\nPerfect for trying new instruments, covering for repairs, or accessing specialized gear for recording projects and performances.",
                    'tabler-user-circle',
                    '**Members Only** — Gear lending requires CMC membership and good standing',
                    'info',
                ),
                $this->card('tabler-guitar-pick', 'Available Equipment', null, [
                    'Electric guitars & basses',
                    'Acoustic instruments',
                    'Amplifiers & effects pedals',
                    'Recording equipment',
                    'Percussion instruments',
                    'Specialty instruments',
                ], 'info'),
            ]),

            // Gear Lending Library - stats
            $this->section('info', 3, false, [
                $this->stat('Borrowing Period', '1-2 Weeks', 'Renewable based on availability', 'base'),
                $this->stat('Security Deposit', 'Varies', 'Refundable upon return', 'base'),
                $this->stat('Rental Fee', 'Low Cost', 'Covers maintenance & replacement', 'base'),
            ]),

            // Gear Lending Library - buttons
            $this->section('info', 2, false, [
                $this->button('Browse Available Gear', '/contact?topic=gear', 'info'),
                $this->button('Donate Equipment', '/contact?topic=gear', 'outline-info'),
            ]),

            // Call to Action - intro + steps
            $this->section('primary', 3, false, [
                $this->header('Ready to Get Involved?', 'Join the Corvallis Music Collective to access all our programs and connect with a vibrant community of musicians.'),
                $this->step('tabler-number-1', 'Join CMC', 'Become a member to access all programs'),
                $this->step('tabler-number-2', 'Choose Your Path', 'Practice, perform, or join our clubs'),
                $this->step('tabler-number-3', 'Make Music', 'Connect and create with the community'),
            ]),

            // Call to Action - buttons
            $this->section('primary', 2, false, [
                $this->button('Become a Member', '/member/register', 'primary'),
                $this->button('Ask Questions', '/contact?topic=general', 'outline-secondary'),
            ]),
        ];
    }

    /**
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $items
     * @return array{type: string, data: array<string, mixed>}
     */
    private function section(string $bg, int $columns, bool $fullBleed, array $items): array
    {
        return [
            'type' => 'section',
            'data' => [
                'background_color' => $bg,
                'columns' => $columns,
                'full_bleed' => $fullBleed,
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function header(string $heading, ?string $description = null, ?string $icon = null, string $colSpan = 'full'): array
    {
        return [
            'type' => 'header',
            'data' => [
                'heading' => $heading,
                'description' => $description,
                'icon' => $icon,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function prose(string $content, ?string $alertIcon = null, ?string $alertText = null, ?string $alertStyle = null, string $colSpan = 'auto'): array
    {
        return [
            'type' => 'prose',
            'data' => [
                'content' => $content,
                'alert_icon' => $alertIcon,
                'alert_text' => $alertText,
                'alert_style' => $alertStyle,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $features
     * @return array{type: string, data: array<string, mixed>}
     */
    private function card(string $icon, string $heading, ?string $body, array $features, string $color = 'base', string $colSpan = 'auto'): array
    {
        return [
            'type' => 'card',
            'data' => [
                'icon' => $icon,
                'heading' => $heading,
                'body' => $body,
                'features' => array_map(fn (string $text) => ['text' => $text], $features),
                'color' => $color,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $details
     * @param  array<int, string>  $activities
     * @return array{type: string, data: array<string, mixed>}
     */
    private function detailedCard(string $name, string $icon, string $iconColor, string $description, array $details, string $activitiesLabel, array $activities, ?string $tip = null, string $colSpan = 'auto'): array
    {
        return [
            'type' => 'detailed_card',
            'data' => [
                'name' => $name,
                'icon' => $icon,
                'icon_color' => $iconColor,
                'description' => $description,
                'details' => $details,
                'activities_label' => $activitiesLabel,
                'activities' => array_map(fn (string $text) => ['text' => $text], $activities),
                'tip' => $tip,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @param  array<int, array{name: string, icon: string, icon_color: string, description: string, badge: string}>  $cards
     * @return array{type: string, data: array<string, mixed>}
     */
    private function cardStack(array $cards, string $colSpan = 'auto'): array
    {
        return [
            'type' => 'card_stack',
            'data' => [
                'cards' => $cards,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function stat(string $label, string $value, ?string $subtitle = null, string $color = 'base', string $colSpan = 'auto'): array
    {
        return [
            'type' => 'stat',
            'data' => [
                'label' => $label,
                'value' => $value,
                'subtitle' => $subtitle,
                'color' => $color,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function step(string $icon, string $title, string $description, string $colSpan = 'auto'): array
    {
        return [
            'type' => 'step',
            'data' => [
                'icon' => $icon,
                'title' => $title,
                'description' => $description,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function button(string $label, string $url, string $style = 'primary', string $colSpan = 'full'): array
    {
        return [
            'type' => 'button',
            'data' => [
                'label' => $label,
                'url' => $url,
                'style' => $style,
                'col_span' => $colSpan,
            ],
        ];
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    private function alert(string $icon, string $text, string $style = 'info', string $colSpan = 'auto'): array
    {
        return [
            'type' => 'alert',
            'data' => [
                'icon' => $icon,
                'text' => $text,
                'style' => $style,
                'col_span' => $colSpan,
            ],
        ];
    }
}
