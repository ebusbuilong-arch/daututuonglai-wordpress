(() => {
    const tool = document.getElementById('dttl-live-mp3');
    const files = document.getElementById('dttl-mp3-files');
    const batch = document.getElementById('dttl-mp3-batch');
    const preview = document.getElementById('dttl-mp3-preview');
    const status = document.getElementById('dttl-mp3-status');
    const rows = document.getElementById('dttl-mp3-rows');
    const form = document.getElementById('dttl-mp3-import');
    const lessonName = (number) => String(number).padStart(3, '0');
    const labels = {
        queued: 'Chờ gửi', sending: 'Đang gửi', processing: 'EverAI đang xử lý',
        ready: 'Có MP3', failed: 'Cần kiểm tra', expired: 'Link hết hạn', uncertain: 'Cần đối chiếu EverAI',
    };

    function validate() {
        let items;
        try {
            items = JSON.parse(batch.value);
        } catch {
            throw new Error('Nội dung chưa đúng định dạng JSON.');
        }
        if (!Array.isArray(items) || !items.length || items.length > 10) {
            throw new Error('Chọn từ 1 đến 10 bài.');
        }
        const seen = new Set();
        let total = 0;
        for (const item of items) {
            if (!item || !Number.isInteger(item.lesson) || item.lesson < 1 || item.lesson > 99999
                || typeof item.text !== 'string' || (item.title !== undefined && typeof item.title !== 'string')) {
                throw new Error('Mỗi bài cần số bài hợp lệ và nội dung dạng văn bản.');
            }
            const count = Array.from(item.text.replace(/\r\n?/g, '\n').trim()).length;
            if (seen.has(item.lesson) || count < 1 || count > 25000) {
                throw new Error('Bài ' + lessonName(item.lesson) + ' bị trùng, trống hoặc vượt 25.000 ký tự.');
            }
            seen.add(item.lesson);
            total += count;
        }
        return items.length + ' bài · ' + total.toLocaleString('vi-VN') + ' ký tự · ' +
            Array.from(seen).sort((a, b) => a - b).map(lessonName).join(', ');
    }

    function showPreview() {
        try {
            preview.textContent = validate();
            batch.setCustomValidity('');
        } catch (error) {
            preview.textContent = error.message;
            batch.setCustomValidity(error.message);
        }
    }

    files.addEventListener('change', async () => {
        const selected = Array.from(files.files);
        if (!selected.length) return;
        try {
            if (selected.length === 1 && /\.json$/i.test(selected[0].name) && selected[0].size <= 1250000) {
                batch.value = await selected[0].text();
            } else {
                if (selected.length > 10) throw new Error('Chọn tối đa 10 file TXT.');
                const items = await Promise.all(selected.map(async (file) => {
                    const match = file.name.match(/^(?:LIVE[-_ ]?)?(\d{1,5})(?:[-_ ].+)?\.txt$/i);
                    if (!match || file.size > 125000) throw new Error('Kiểm tra tên hoặc dung lượng file ' + file.name + '.');
                    return {
                        lesson: Number(match[1]),
                        title: file.name.slice(match[0].indexOf(match[1]) + match[1].length, -4)
                            .replace(/^[-_ ]+/, '').replace(/[-_]/g, ' '),
                        text: await file.text(),
                    };
                }));
                batch.value = JSON.stringify(items, null, 2);
            }
            showPreview();
        } catch (error) {
            preview.textContent = error.message;
        }
    });

    batch.addEventListener('input', showPreview);
    form.addEventListener('submit', (event) => {
        showPreview();
        if (!batch.reportValidity()) event.preventDefault();
    });

    async function request(action, extra = {}) {
        const response = await fetch(window.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ action, nonce: window.dttlLiveMp3.nonce, page: tool.dataset.page, ...extra }),
        });
        const result = await response.json();
        if (!result.success) throw new Error(typeof result.data === 'string' ? result.data : 'Tải lại trang để cập nhật.');
        return result.data;
    }

    function render(jobs) {
        const elements = jobs.map((job) => {
            const number = lessonName(job.lesson);
            const tr = document.createElement('tr');
            for (const value of [number, job.title || 'Bài ' + number, labels[job.state] || job.state]) {
                tr.insertCell().textContent = value;
            }
            const details = [job.state === 'processing' && job.progress > 0 ? job.progress + '%' : '', job.detail].filter(Boolean);
            if (details.length) {
                const note = document.createElement('small');
                note.textContent = details.join(' · ');
                tr.cells[2].append(note);
            }
            const cell = tr.insertCell();
            if (job.state === 'ready') {
                const link = document.createElement('a');
                const url = new URL(tool.dataset.download, window.location.href);
                url.searchParams.set('lesson', job.lesson);
                url.searchParams.set('_wpnonce', job.download_nonce);
                link.href = url.href;
                link.className = 'button';
                link.textContent = 'Tải ' + number + '.mp3';
                cell.append(link);
            } else if (job.state === 'failed' || job.state === 'expired') {
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'button';
                retry.textContent = 'Tạo lại';
                retry.addEventListener('click', async () => {
                    if (!window.confirm('Gửi lại bài ' + number + ' sang EverAI có thể dùng thêm credit. Tạo lại?')) return;
                    retry.disabled = true;
                    try {
                        await request('dttl_live_mp3_retry', { lesson: job.lesson });
                        clearTimeout(timer);
                        refresh();
                    } catch (error) {
                        status.textContent = error.message;
                        retry.disabled = false;
                    }
                });
                cell.append(retry);
            }
            return tr;
        });
        if (!elements.length) {
            const empty = document.createElement('tr');
            const cell = empty.insertCell();
            cell.colSpan = 4;
            cell.textContent = 'Chưa có bài trong hàng đợi.';
            elements.push(empty);
        }
        rows.replaceChildren(...elements);
    }

    let timer;
    let refreshing = false;
    async function refresh() {
        if (refreshing) return;
        refreshing = true;
        let next = 30000;
        try {
            const data = await request('dttl_live_mp3_tick');
            render(data.rows);
            const pending = data.rows.filter((job) => ['queued', 'sending', 'processing'].includes(job.state)).length;
            const ready = data.rows.filter((job) => job.state === 'ready').length;
            status.textContent = pending ? pending + ' bài đang chờ hoặc đang xử lý · ' + ready + ' bài có MP3.'
                : ready ? ready + ' bài có MP3 trên trang này.' : 'Chưa có MP3 trên trang này.';
            if (pending) next = 5000;
        } catch (error) {
            status.textContent = 'Chưa cập nhật được hàng đợi. ' + error.message;
        } finally {
            refreshing = false;
            timer = setTimeout(refresh, next);
        }
    }
    refresh();
})();
