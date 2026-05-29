<x-admin-layout>
    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Review Participant Matches</h1>
            <p class="mt-1 text-sm text-gray-600">
                Matched participants are shown below. For unmatched names, check the box to create a recipient account for them. Unchecked names will be skipped and their certificates issued without a linked account.
            </p>
        </div>

        @if (session('warning'))
            <div class="mb-6 rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800">
                {{ session('warning') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.certs.matching-resolve') }}">
            @csrf

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 w-10">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Participant</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Match Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 w-32">Create Account</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($participants as $i => $participant)
                            @php
                                $result = $results[$i] ?? ['recipient_id' => null, 'confidence' => 'no_match', 'ambiguous' => false, 'candidates' => []];
                                $isMatched = $result['recipient_id'] !== null;
                            @endphp
                            <tr class="{{ $isMatched ? 'bg-green-50/30' : '' }}">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900">{{ $participant['name'] ?? 'Unknown' }}</div>
                                    @if (!empty($participant['email']))
                                        <div class="text-xs text-gray-500">{{ $participant['email'] }}</div>
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
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                            No Match
                                        </span>
                                        @if (!empty($participant['email']))
                                            <span class="ml-2 text-xs text-gray-400">Will create dormant account with email</span>
                                        @else
                                            <span class="ml-2 text-xs text-gray-400">Name only — no email provided</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($isMatched)
                                        <input type="hidden" name="matches[{{ $i }}]" value="accept_{{ $result['recipient_id'] }}">
                                        <span class="text-xs text-green-600">Auto</span>
                                    @elseif (!empty($participant['email']))
                                        <input type="hidden" name="matches[{{ $i }}]" value="skip">
                                        <input type="checkbox"
                                               name="matches[{{ $i }}]"
                                               value="create"
                                               class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                               @checked(true)>
                                    @else
                                        <input type="hidden" name="matches[{{ $i }}]" value="skip">
                                        <span class="text-xs text-gray-400">No email</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <a href="{{ route('admin.certs.create') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    Cancel and start over
                </a>
                <button type="submit"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Proceed to Endorsement
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
