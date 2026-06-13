<x-admin-layout>
    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Review Participant Matches</h1>
            <p class="mt-1 text-sm text-gray-600">
                Matched participants are shown below. For unmatched names, you can either select an existing recipient
                or create a new account. Unresolved names will be skipped and their certificates issued without a linked account.
            </p>
        </div>

        @if (session('warning'))
            <div class="mb-6 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 border border-yellow-200">
                {{ session('warning') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.certs.matching-resolve') }}" x-data="{
            selections: {},
            init() {
                @foreach ($participants as $i => $participant)
                    @php
                        $result = $results[$i] ?? ['recipient_id' => null, 'confidence' => 'no_match', 'ambiguous' => false, 'candidates' => []];
                        $isMatched = $result['recipient_id'] !== null;
                        $hasEmail = !empty($participant['email']);
                    @endphp
                    @if (!$isMatched && $hasEmail)
                        this.selections[{{ $i }}] = 'create';
                    @else
                        this.selections[{{ $i }}] = 'skip';
                    @endif
                @endforeach
            },
            selectCandidate(index, recipientId) {
                const el = document.getElementById('match_select_' + index);
                el.value = 'accept_' + recipientId;
                this.selections[index] = 'accept_' + recipientId;
            }
        }">
            @csrf

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 w-10">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Participant</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Match Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($participants as $i => $participant)
                            @php
                                $result = $results[$i] ?? ['recipient_id' => null, 'confidence' => 'no_match', 'ambiguous' => false, 'candidates' => []];
                                $isMatched = $result['recipient_id'] !== null;
                                $isAmbiguous = !$isMatched && ($result['ambiguous'] ?? false) && !empty($result['candidates']);
                                $hasEmail = !empty($participant['email']);
                            @endphp
                            <tr class="{{ $isMatched ? 'bg-green-50/30' : ($isAmbiguous ? 'bg-yellow-50/30' : '') }}">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $participant['name'] ?? 'Unknown' }}</div>
                                    @if ($hasEmail)
                                        <div class="text-xs text-gray-500">{{ $participant['email'] }}</div>
                                    @endif
                                    @if (!empty($participant['contact_number']))
                                        <div class="text-xs text-gray-400">{{ $participant['contact_number'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($isMatched)
                                        @php $matchedRecipient = \App\Models\Recipient::find($result['recipient_id']); @endphp
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                            Matched
                                        </span>
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ $matchedRecipient ? $matchedRecipient->name : 'Unknown' }}
                                        </span>
                                        @if ($matchedRecipient && $matchedRecipient->email)
                                            <span class="ml-1 text-xs text-gray-400">({{ $matchedRecipient->email }})</span>
                                        @endif
                                    @elseif ($isAmbiguous)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                            Multiple Candidates
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                            No Match
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($isMatched)
                                        {{-- Already matched — keep as-is --}}
                                        <input type="hidden" name="matches[{{ $i }}]" value="accept_{{ $result['recipient_id'] }}">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 rounded-full px-2.5 py-0.5">
                                            Auto-matched
                                        </span>
                                    @else
                                        {{-- Unmatched — show resolution options --}}
                                        <div class="space-y-2">
                                            @if ($isAmbiguous)
                                                {{-- Ambiguous: show candidate quick-select buttons --}}
                                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                                    <span class="text-xs text-yellow-700 font-medium">Suggestions:</span>
                                                    @foreach ($result['candidates'] as $cName => $cId)
                                                        @php
                                                            $cRecipient = \App\Models\Recipient::find($cId);
                                                        @endphp
                                                        @if ($cRecipient)
                                                            <button type="button"
                                                                    x-on:click="selectCandidate({{ $i }}, {{ $cRecipient->id }})"
                                                                    class="inline-flex items-center rounded-md border border-yellow-300 bg-yellow-50 px-2.5 py-1 text-xs font-medium text-yellow-800 shadow-sm hover:bg-yellow-100 hover:border-yellow-400 transition-colors cursor-pointer">
                                                                {{ $cRecipient->name }}
                                                                @if ($cRecipient->email)
                                                                    <span class="ml-1 opacity-70">({{ $cRecipient->email }})</span>
                                                                @endif
                                                            </button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Recipient selection dropdown --}}
                                            <select id="match_select_{{ $i }}"
                                                    name="matches[{{ $i }}]"
                                                    x-model="selections[{{ $i }}]"
                                                    class="block w-full rounded-md border-2 border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none transition-colors">
                                                <option value="skip">
                                                    — Skip (no linked account) —
                                                </option>
                                                @if ($hasEmail)
                                                    <option value="create">
                                                        ✚ Create new account for {{ $participant['name'] ?? 'this person' }}
                                                    </option>
                                                @endif
                                                <optgroup label="── Existing Recipients ──">
                                                    @foreach ($allRecipients as $recipient)
                                                        @php
                                                            $label = $recipient->name;
                                                            if ($recipient->email) {
                                                                $label .= ' — ' . $recipient->email;
                                                            }
                                                        @endphp
                                                        <option value="accept_{{ $recipient->id }}">
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Action buttons --}}
            <div class="mt-6 flex items-center justify-between">
                <a href="{{ route('admin.certs.create') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 hover:text-gray-900 transition-colors">
                    ← Cancel and start over
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors cursor-pointer">
                    Proceed to Endorsement →
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
