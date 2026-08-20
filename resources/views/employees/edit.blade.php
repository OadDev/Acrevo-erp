<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Worker" :subtitle="$employee->employee_code" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('employees._form')
        </form>
    </x-card>
</x-app-layout>
