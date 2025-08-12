{{-- Activity Toggle Button for Header --}}
<div class="flex items-center">
    <button 
        type="button"
        x-data
        @click="$dispatch('toggle-activity-sidebar')"
        class="flex items-center justify-center w-9 h-9 text-gray-400 transition duration-75 rounded-lg outline-none hover:text-gray-500 hover:bg-gray-50 focus:bg-gray-50 focus:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 dark:hover:bg-white/5 dark:focus:bg-white/5 dark:focus:text-gray-400"
        title="View Activity"
    >
        <x-tabler-activity class="w-5 h-5" />
    </button>
</div>