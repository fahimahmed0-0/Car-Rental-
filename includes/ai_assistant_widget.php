<?php

declare(strict_types=1);
?>
<div x-data="driveXAssistant()" class="fixed bottom-5 right-5 z-40 flex flex-col items-end gap-3 print:hidden">
  <!-- Chat Drawer -->
  <div x-show="open" x-transition style="display:none;"
       class="w-80 sm:w-96 rounded-2xl border border-slate-200 bg-white shadow-2xl overflow-hidden flex flex-col">
    <div class="shrink-0 bg-slate-950 text-white px-4 py-3 flex items-center justify-between border-b-2 border-red-600">
      <div>
        <div class="text-sm font-extrabold">DriveX Rental Assistant</div>
        <div class="text-[11px] text-sky-300" x-text="engineLabel">Ask about cars, tokens, or policies</div>
      </div>
      <button type="button" @click="open = false" class="text-slate-400 hover:text-white text-lg leading-none" aria-label="Close assistant">&times;</button>
    </div>

    <div class="h-64 overflow-y-auto p-4 space-y-3 bg-slate-50 text-xs" id="assistant-messages">
      <template x-for="(m, i) in messages" :key="i">
        <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
          <div :class="m.role === 'user' ? 'bg-blue-700 text-white' : 'bg-white text-slate-800 border border-slate-200'"
               class="max-w-[85%] rounded-xl px-3.5 py-2.5 leading-relaxed shadow-sm"
               x-text="m.text"></div>
        </div>
      </template>
    </div>

    <div class="shrink-0 border-t border-slate-200 bg-white p-3">
      <div class="mb-2 flex flex-wrap gap-1.5">
        <button type="button" @click="quickAsk('How do loyalty tokens work?')" class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-semibold text-blue-800 hover:bg-blue-100">Token rules</button>
        <button type="button" @click="quickAsk('What is the cancellation policy?')" class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-semibold text-blue-800 hover:bg-blue-100">Cancellation</button>
        <button type="button" @click="quickAsk('Where is your Guwahati hub?')" class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-semibold text-blue-800 hover:bg-blue-100">Hub address</button>
      </div>
      <form @submit.prevent="send()" class="flex gap-2">
        <input type="text" x-model="input" placeholder="Ask a question..." class="form-input !py-2 !text-xs">
        <button type="submit" :disabled="loading" class="btn-primary !py-2 !px-3.5 !text-xs shrink-0">Send</button>
      </form>
    </div>
  </div>

  <!-- Launcher Button -->
  <button type="button" @click="open = !open"
          class="flex items-center gap-2 rounded-full bg-blue-700 px-4 py-3 text-xs font-bold text-white shadow-lg hover:bg-blue-800 border border-blue-600 transition">
    <span class="h-2 w-2 rounded-full bg-sky-300"></span>
    <span x-text="open ? 'Close Help' : 'DriveX Assistant'"></span>
  </button>
</div>

<script>
function driveXAssistant() {
  return {
    open: false,
    input: '',
    loading: false,
    engineLabel: 'Fleet availability, pricing & booking policies',
    messages: [
      { role: 'bot', text: 'Hello! Ask me about daily ₹ prices, loyalty tokens, cancellation refunds, or our Guwahati pickup hub.' }
    ],
    quickAsk(q) {
      this.input = q;
      this.send();
    },
    async send() {
      var q = this.input.trim();
      if (!q || this.loading) return;
      this.messages.push({ role: 'user', text: q });
      this.input = '';
      this.loading = true;
      var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      try {
        var res = await fetch('api/v1/ai.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'assistant', question: q })
        });
        var data = await res.json();
        this.messages.push({ role: 'bot', text: data.reply || data.message || 'Please check our Fleet page for details.' });
        if (data.tier_label) this.engineLabel = data.tier_label;
      } catch (e) {
        this.messages.push({ role: 'bot', text: 'Unable to reach assistant right now. Please try again.' });
      } finally {
        this.loading = false;
        this.$nextTick(function() {
          var box = document.getElementById('assistant-messages');
          if (box) box.scrollTop = box.scrollHeight;
        });
      }
    }
  };
}
</script>
