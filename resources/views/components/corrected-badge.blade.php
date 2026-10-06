{{--
    "Corrected by Psychometrician" — shown to the Guidance Counselor when the
    Psychometrician's Step 3 review really changed the AI's classification
    (Assessment::wasCorrected()). It marks the whole assessment and carries
    no level, no direction and no data- attributes, so the AI's raw proposal
    can't be read from it. Slate, so it doesn't borrow a severity or flag colour.
--}}
<x-badge color="slate" title="The Psychometrician reviewed and changed the AI's classification before saving. Levels shown are the reviewed ones." {{ $attributes }}>
    <svg class="h-3.5 w-3.5 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 1 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM11.379 5.793 3 14.172V17h2.828l8.38-8.379-2.83-2.828Z" /></svg>
    Corrected by Psychometrician
</x-badge>
