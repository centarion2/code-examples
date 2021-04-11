let page = 1;
let pageBonus = 1;
const loadAjaxBonus = document.querySelector('.lk__main');
if (loadAjaxBonus) {
    loadAjaxBonus.addEventListener('click', (evt) => {
        const { target }  = evt;
		if (target) {
			//LoadMore
			if(target.getAttribute('id') === 'load-orders') {
                const formData = new FormData();
                formData.append('action', 'load_orders');
                formData.append('page', page)
                fetch('/local/ajax/bp.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                }).then(res => res.json())
                .then(res => {
                        page++;
                        if (res.items && Array.isArray(res.items)) {
                            res.items.forEach(item => {
                                const itemTemplate = document.createElement('div');
                                itemTemplate.classList.add('lk-bonus__table-line');
                                let className = 'bonus-baget--succ'
                                if(item.status === 'not_available') {
                                    className = 'bonus-baget--warm'
                                } else if (item.status === 'none') {
                                    className = 'bonus-baget--pending'
                                } 

                                let totalBonus = `<span class="orange-text">Будет доступно с ${item.date_available}</span>`
                                if(item.status === "available" || item.status === "none") {
                                    totalBonus = '<span>' + item.bonus_available + '</span>';
                                }
                                itemTemplate.innerHTML = `
                                    <span>${item.name}</span>
                                    <span>${item.date}</span>
                                    <span>${item.sum} ₽</span>
                                    <span>${item.sum_credit} ₽</span>
                                    <span class="bonus-baget ${className}">${item.bonus_available}</span>
                                    <button class="bonus-table-btn">
                                        <svg class="bonus-table-btn-ico" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
                                            <path d="M237.5 85L134.3 188.4a8 8 0 01-5.7 2.4 8 8 0 01-5.7-2.4L19.7 85.1a8 8 0 010-11.3l4.2-4.3a8 8 0 0111.3 0l93.4 93.4L222 69.5a8 8 0 0111.3 0l4.2 4.3a8 8 0 010 11.3z"></path>
                                            </svg>
                                    </button>
                                    <div class="lk-bonus__table-detail">
                                        <div class="lk-bonus__table-detail-bl lk-bonus__table-detail-bl--first">
                                            <b class="lk-bonus__detail-title">Покупка</b>
                                            <div class="lk-bonus__detail-item"><span>Сумма накладной</span><span>${item.sum} ₽</span></div>
                                            <div class="lk-bonus__detail-item"><span>Сумма возврата</span><span>${item.sum_return} ₽</span></div>
                                        </div>
                                        <div class="lk-bonus__table-detail-bl">
                                            <b class="lk-bonus__detail-title">Бонусы</b>
                                            <div class="lk-bonus__detail-item"><span>Всего</span><span>${item.bonus_full}</span></div>
                                            <div class="lk-bonus__detail-item"><span>Использовано</span><span>${item.bonus_spent}</span></div>
                                        </div>
                                        <div class="lk-bonus__table-detail-bl--line"></div>
                                        <div class="lk-bonus__table-detail-bl lk-bonus__table-detail-bl--first">
                                            <div class="lk-bonus__detail-item"><span>Сумма к начислению</span><span>${item.sum_credit} ₽</span></div>
            
                                        </div>
                                        <div class="lk-bonus__table-detail-bl">
                                            <div class="lk-bonus__detail-item"><span>Доступно</span>${totalBonus}</div>
                                        </div>
                                    </div>`;
                                console.log(item);
                                document.querySelector('.lk-bonus__table').appendChild(itemTemplate);
                            })
                        }
                        if (res.last) {
                            target.remove();
                        }
                });
            }

            if(target.getAttribute('id') === 'load-bonus') {
                const formData = new FormData();
                formData.append('action', 'load_bonus');
                formData.append('page', pageBonus)
                fetch('/local/ajax/bp.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                }).then(res => res.json())
                .then(res => {
                        pageBonus++;
                        if (res.items && Array.isArray(res.items)) {
                            res.items.forEach(item => {
                                const itemTemplate = document.createElement('div');
                                itemTemplate.classList.add('lk-bonus__table-line');
                                itemTemplate.innerHTML = `
                                    <span>${item.date}</span>
                                    <span>${item.price}</span>
                                    <div class="lk-bonus__present-wrap">
                                        <p>${item.card_text} (${item.nominal}  ₽)</p>
                                        <img class="lk-bonus__present-img" src="${item.picture}" alt="">
                                    </div>`;
                                document.querySelector('.lk-bonus__table.lk-bonus__table--present').appendChild(itemTemplate);
                            })
                        }
                        if (res.last) {
                            target.remove();
                        }
                });
            }

            if (target.classList.contains('bonus-table-btn')) {
                const panel = target.parentElement;
                if (panel) {
                    panel.classList.toggle("active");
                    panel.style.maxHeight = panel.classList.contains('active') ? panel.scrollHeight + 16 + "px" : '';
                }
            }
        }
    });
}

 