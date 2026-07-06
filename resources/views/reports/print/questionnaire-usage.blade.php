<x-report-layout title="Questionnaire Usage Report">
    <table>
        <thead>
            <tr>
                <th>Questionnaire</th>
                <th>Version</th>
                <th>Status</th>
                <th>Questions</th>
                <th>Assessments Used</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($versions as $version)
                <tr>
                    <td>{{ $version->questionnaire->title }}</td>
                    <td>v{{ $version->version_number }}</td>
                    <td>{{ $version->status }}</td>
                    <td>{{ $version->questions_count }}</td>
                    <td>{{ $version->assessments_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">No questionnaire versions found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</x-report-layout>
