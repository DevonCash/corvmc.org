<x-public.layout title="{{ $page->title }} | Corvallis Music Collective">
    @foreach($groups as $group)
        <x-blocks.section-wrapper :bg="$group['bg']">
            @foreach($group['sections'] as $section)
                @php $data = $section['data']; @endphp

                <x-blocks.section-row
                    :columns="$data['columns'] ?? 2"
                    :fullBleed="$data['full_bleed'] ?? false"
                >
                    @foreach($section['items'] ?? [] as $item)
                        @php
                            $itemData = $item['data'] ?? [];
                            $colSpan = $itemData['col_span'] ?? (in_array($item['type'], ['header', 'button']) ? 'full' : 'auto');
                            $spanClass = match($colSpan) {
                                'full' => 'col-span-full',
                                '2' => 'col-span-2',
                                '3' => 'col-span-3',
                                default => '',
                            };
                        @endphp

                        @if($item['type'] === 'button')
                            @if($loop->first || ($section['items'][$loop->index - 1]['type'] ?? '') !== 'button')
                                <div class="col-span-full text-center flex gap-4 justify-center">
                            @endif

                            <x-blocks.button
                                :label="$itemData['label']"
                                :url="$itemData['url']"
                                :color="$itemData['color'] ?? 'primary'"
                                :variant="$itemData['variant'] ?? 'solid'"
                            />

                            @if($loop->last || ($section['items'][$loop->index + 1]['type'] ?? '') !== 'button')
                                </div>
                            @endif
                        @else
                            <div class="{{ $spanClass }}">
                                @switch($item['type'])
                                    @case('header')
                                        <x-blocks.header
                                            :heading="$itemData['heading']"
                                            :description="$itemData['description'] ?? null"
                                            :icon="$itemData['icon'] ?? null"
                                        />
                                        @break

                                    @case('prose')
                                        <x-blocks.prose :content="$itemData['content']" />
                                        @break

                                    @case('card')
                                        <x-blocks.card
                                            :icon="$itemData['icon'] ?? null"
                                            :heading="$itemData['heading']"
                                            :body="$itemData['body'] ?? null"
                                            :color="$itemData['color'] ?? 'base'"
                                        />
                                        @break

                                    @case('alert')
                                        <x-blocks.alert
                                            :icon="$itemData['icon'] ?? null"
                                            :text="$itemData['text']"
                                            :style="$itemData['style'] ?? 'info'"
                                        />
                                        @break

                                    @case('stat')
                                        <x-blocks.stat
                                            :label="$itemData['label']"
                                            :value="$itemData['value']"
                                            :subtitle="$itemData['subtitle'] ?? null"
                                            :color="$itemData['color'] ?? 'base'"
                                        />
                                        @break

                                    @case('step')
                                        <x-blocks.step
                                            :icon="$itemData['icon'] ?? null"
                                            :title="$itemData['title']"
                                            :description="$itemData['description'] ?? null"
                                        />
                                        @break
                                @endswitch
                            </div>
                        @endif
                    @endforeach
                </x-blocks.section-row>
            @endforeach
        </x-blocks.section-wrapper>
    @endforeach
</x-public.layout>
