<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Raw Zeffy Webhook Data
        </h3>
        <pre class="text-xs text-gray-600 dark:text-gray-400 whitespace-pre-wrap overflow-auto max-h-96 bg-white dark:bg-gray-800 p-3 rounded border">{{ json_encode($transaction->response, JSON_PRETTY_PRINT) }}</pre>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-3">
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Transaction ID:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $transaction->transaction_id }}</span>
            </div>
            
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Amount:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">${{ number_format($transaction->amount, 2) }} {{ $transaction->currency }}</span>
            </div>
            
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Email:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ $transaction->email }}</span>
            </div>
            
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Type:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ ucfirst($transaction->type) }}</span>
            </div>
        </div>
        
        <div class="space-y-3">
            @if(isset($transaction->response['donor_name']))
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Donor Name:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ $transaction->response['donor_name'] }}</span>
            </div>
            @endif
            
            @if(isset($transaction->response['campaign']))
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Campaign:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $transaction->response['campaign'])) }}</span>
            </div>
            @endif
            
            @if(isset($transaction->response['payment_method']))
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Payment Method:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $transaction->response['payment_method'])) }}</span>
            </div>
            @endif
            
            @if(isset($transaction->response['timestamp']))
            <div>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Zeffy Timestamp:</span>
                <span class="block text-sm text-gray-600 dark:text-gray-400">{{ $transaction->response['timestamp'] }}</span>
            </div>
            @endif
        </div>
    </div>
    
    @if(isset($transaction->response['note']))
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <h4 class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-1">Note</h4>
        <p class="text-sm text-blue-600 dark:text-blue-400">{{ $transaction->response['note'] }}</p>
    </div>
    @endif
    
    @if(isset($transaction->response['memorial_note']))
    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
        <h4 class="text-sm font-medium text-purple-700 dark:text-purple-300 mb-1">Memorial Note</h4>
        <p class="text-sm text-purple-600 dark:text-purple-400">{{ $transaction->response['memorial_note'] }}</p>
        @if(isset($transaction->response['honoree_name']))
        <p class="text-sm text-purple-600 dark:text-purple-400 mt-1">
            <strong>In memory of:</strong> {{ $transaction->response['honoree_name'] }}
        </p>
        @endif
    </div>
    @endif
    
    @if(isset($transaction->response['additional_questions']) && !empty($transaction->response['additional_questions']))
    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
        <h4 class="text-sm font-medium text-green-700 dark:text-green-300 mb-3">Additional Questions</h4>
        <div class="space-y-2">
            @foreach($transaction->response['additional_questions'] as $question => $answer)
            <div>
                <span class="text-sm font-medium text-green-700 dark:text-green-300">{{ $question }}:</span>
                <span class="block text-sm text-green-600 dark:text-green-400 mt-1">{{ $answer }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>