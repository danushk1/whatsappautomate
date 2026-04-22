<div class="font-sans antialiased h-[calc(100vh-4rem)] bg-gray-50" x-data>
    <div class="flex h-full border-t">
        <!-- Sidebar -->
        <div class="flex flex-col w-1/4 bg-white border-r">
            <div class="p-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">Conversations</h2>
            </div>
            <div class="overflow-y-auto">
                <ul>
                    @forelse ($conversations as $phone)
                        <li wire:click="selectConversation('{{ $phone }}')"
                            class="p-4 cursor-pointer hover:bg-gray-100 border-b {{ $selectedConversation === $phone ? 'bg-sky-100' : '' }}">
                            <div class="flex items-center">
                                <div class="relative w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3 text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $phone }}</p>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="p-4 text-center text-gray-500">No conversations yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="flex flex-col w-3/4">
            @if ($selectedConversation)
                <!-- Header -->
                <div class="flex items-center p-4 bg-white border-b shadow-sm">
                    <div class="relative w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3 text-white">
                         <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $selectedConversation }}</h3>
                </div>

                <!-- Messages -->
                <div id="chat-box" class="flex-1 p-6 overflow-y-auto bg-gray-100">
                    <div class="space-y-4">
                        @foreach ($messages as $message)
                            @if ($message->role === 'agent')
                                <!-- Outgoing Message (Agent) -->
                                <div class="flex justify-end">
                                    <div class="max-w-lg px-4 py-2 text-white bg-sky-500 rounded-xl shadow">
                                        <p class="text-sm">{{ $message->content }}</p>
                                        <span class="text-xs text-sky-100 block text-right mt-1">{{ \Carbon\Carbon::parse($message->created_at)->format('h:i A') }}</span>
                                    </div>
                                </div>
                            @else
                                <!-- Incoming Message (User) -->
                                <div class="flex justify-start">
                                    <div class="max-w-lg px-4 py-2 text-gray-700 bg-white rounded-xl shadow">
                                        <p class="text-sm">{{ $message->content }}</p>
                                        <span class="text-xs text-gray-400 block text-right mt-1">{{ \Carbon\Carbon::parse($message->created_at)->format('h:i A') }}</span>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <!-- Input -->
                <div class="p-4 bg-white border-t">
                    <form wire:submit="sendMessage" class="flex items-center">
                        <input wire:model="newMessage" type="text" placeholder="Type a message..." autocomplete="off"
                            class="flex-1 px-4 py-2 border rounded-full focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <button type="submit" class="ml-4 p-3 text-white bg-sky-500 rounded-full hover:bg-sky-600 focus:outline-none transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </button>
                    </form>
                </div>
            @else
                <div class="flex items-center justify-center h-full text-gray-500">
                    <div class="text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <p class="mt-2 text-lg">Select a conversation to start chatting</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('livewire:navigated', () => {
            const chatBox = document.getElementById('chat-box');

            const scrollToBottom = () => {
                if(chatBox) {
                    chatBox.scrollTop = chatBox.scrollHeight;
                }
            }

            // Initial load and when a new conversation is selected
            scrollToBottom();

            // Listen for events from Livewire component
            Livewire.on('conversationSelected', () => {
                // Wait for the DOM to update
                setTimeout(() => scrollToBottom(), 50);
            });
            Livewire.on('messageReceived', () => {
                setTimeout(() => scrollToBottom(), 50);
            });
            Livewire.on('messageSent', () => {
                setTimeout(() => scrollToBottom(), 50);
            });
        });
    </script>
</div>
