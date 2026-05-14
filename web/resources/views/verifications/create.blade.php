<x-layouts.app title="New TrueSeal Scan">
    <div class="mb-8 animate-fade-in-up">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-squad-crimson">New verification</p>
        <h1 class="mt-2 text-3xl font-bold text-white">Upload certificate</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Image uploads are held privately, payment is confirmed, then the forensic engine generates the heatmap and verdict.</p>
    </div>

    <form method="POST" action="{{ route('verifications.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[1fr_0.75fr]">
        @csrf

        <section class="animate-fade-in-up delay-1 glass rounded-xl p-6">
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="block sm:col-span-2">
                    <span class="text-sm font-medium text-slate-300">Issuing university</span>
                    <select name="institution_id" required class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-3 text-sm text-white outline-none transition focus:border-squad-crimson focus:ring-1 focus:ring-squad-crimson/30">
                        <option value="">Select institution</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}" @selected(old('institution_id') == $institution->id)>{{ $institution->name }}</option>
                        @endforeach
                    </select>
                    @error('institution_id')<span class="mt-2 block text-sm text-red-300">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-300">Candidate name</span>
                    <input name="candidate_name" value="{{ old('candidate_name') }}" required class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-3 text-sm text-white outline-none transition focus:border-squad-crimson focus:ring-1 focus:ring-squad-crimson/30">
                    @error('candidate_name')<span class="mt-2 block text-sm text-red-300">{{ $message }}</span>@enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-300">Candidate email</span>
                    <input name="candidate_email" type="email" value="{{ old('candidate_email') }}" required class="mt-2 w-full rounded-lg border border-white/10 bg-slate-950/80 px-3 py-3 text-sm text-white outline-none transition focus:border-squad-crimson focus:ring-1 focus:ring-squad-crimson/30">
                    @error('candidate_email')<span class="mt-2 block text-sm text-red-300">{{ $message }}</span>@enderror
                </label>

                {{-- Drag-and-drop upload zone --}}
                <div class="sm:col-span-2">
                    <span class="text-sm font-medium text-slate-300">Certificate image</span>
                    <div id="drop-zone" class="group mt-2 cursor-pointer rounded-xl border-2 border-dashed border-white/15 bg-slate-950/60 px-6 py-10 text-center transition hover:border-squad-crimson/40 hover:bg-squad-crimson/[0.02]">
                        <input name="document" id="file-input" type="file" accept="image/png,image/jpeg,image/webp" required class="hidden">

                        <div id="upload-prompt">
                            <svg class="mx-auto h-10 w-10 text-slate-500 transition group-hover:text-squad-crimson/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                            <p class="mt-3 text-sm font-medium text-slate-300">Drop your certificate here or <span class="text-squad-crimson underline underline-offset-2">browse files</span></p>
                            <p class="mt-1 text-xs text-slate-500">PNG, JPEG, or WebP &middot; Max 10 MB</p>
                        </div>

                        <div id="upload-preview" class="hidden">
                            <img id="preview-img" src="" alt="Preview" class="mx-auto h-40 rounded-lg border border-white/10 object-contain">
                            <p id="preview-name" class="mt-2 text-sm font-medium text-white"></p>
                            <p class="mt-1 text-xs text-squad-crimson">Click to change file</p>
                        </div>
                    </div>
                    @error('document')<span class="mt-2 block text-sm text-red-300">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('dashboard') }}" class="rounded-lg border border-white/10 px-4 py-3 text-sm text-slate-300 transition hover:border-white/20 hover:text-white">Cancel</a>
                <button class="rounded-lg bg-squad-crimson px-5 py-3 text-sm font-bold text-white transition hover:bg-squad-crimson/85 hover:shadow-lg hover:shadow-squad-crimson/20 active:scale-[0.98]">Continue to payment</button>
            </div>
        </section>

        <aside class="animate-fade-in-up delay-2 glass rounded-xl p-6">
            <h2 class="text-base font-bold text-white">Fee allocation</h2>
            <p class="mt-1 text-xs text-slate-500">Powered by Squad Payment API</p>
            <div class="mt-5 space-y-4">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <span class="text-sm text-slate-400">Forensic verification</span>
                    <span class="text-lg font-bold text-white">₦5,000</span>
                </div>
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <span class="text-sm text-slate-400">TrueSeal service fee</span>
                    <span class="font-semibold text-white">₦4,000</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-400">University royalty</span>
                    <span class="font-semibold text-cyan-200">₦1,000</span>
                </div>
            </div>
            <div class="mt-5 rounded-lg border border-cyan-300/20 bg-cyan-300/5 p-3">
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-cyan-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-xs leading-relaxed text-cyan-100/70">The ₦1,000 royalty is <strong class="text-white">automatically transferred</strong> to the issuing university's bank account after payment clears, via Squad Transfer API.</p>
                </div>
            </div>

            {{-- How it works --}}
            <div class="mt-6 border-t border-white/[0.06] pt-4">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">How it works</h3>
                <ol class="mt-3 space-y-2 text-xs text-slate-400">
                    <li class="flex items-start gap-2">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-squad-crimson/10 text-[10px] font-bold text-squad-crimson">1</span>
                        <span>Upload certificate and pay ₦5,000 via Squad</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-squad-crimson/10 text-[10px] font-bold text-squad-crimson">2</span>
                        <span>AI engine runs ELA + OCR forensic analysis</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-squad-crimson/10 text-[10px] font-bold text-squad-crimson">3</span>
                        <span>₦1,000 royalty auto-routed to university bank</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-squad-crimson/10 text-[10px] font-bold text-squad-crimson">4</span>
                        <span>View verdict with heatmap and manipulation evidence</span>
                    </li>
                </ol>
            </div>
        </aside>
    </form>

    <script>
        (() => {
            const zone = document.getElementById('drop-zone');
            const input = document.getElementById('file-input');
            const prompt = document.getElementById('upload-prompt');
            const preview = document.getElementById('upload-preview');
            const previewImg = document.getElementById('preview-img');
            const previewName = document.getElementById('preview-name');

            zone.addEventListener('click', () => input.click());

            zone.addEventListener('dragover', e => {
                e.preventDefault();
                zone.classList.add('border-squad-crimson/50', 'bg-squad-crimson/5');
            });

            zone.addEventListener('dragleave', () => {
                zone.classList.remove('border-squad-crimson/50', 'bg-squad-crimson/5');
            });

            zone.addEventListener('drop', e => {
                e.preventDefault();
                zone.classList.remove('border-squad-crimson/50', 'bg-squad-crimson/5');
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    showPreview(e.dataTransfer.files[0]);
                }
            });

            input.addEventListener('change', () => {
                if (input.files.length) showPreview(input.files[0]);
            });

            function showPreview(file) {
                const reader = new FileReader();
                reader.onload = e => {
                    previewImg.src = e.target.result;
                    previewName.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)';
                    prompt.classList.add('hidden');
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        })();
    </script>
</x-layouts.app>
