let durationCode = 30;
const timers = [];
let currenID = null;

function normalizeDig (number) {
  return number < 10 ? '0' + number : number;
}

function formateTime (min, sec, currSec) {
  if(currSec === 0 && min > 0) {
    min -= 1;
    sec = '59';
  } else {
    sec -= 1;
  }
  return {
    time: '('+ min +':' + normalizeDig(sec)+')',
    second: sec,
    minutes: min
  };
};

function setTimeCode (nodeCode) {
  nodeCode.parentElement.classList.remove('active');
  let time = Number(durationCode) ? Number(durationCode) :  30;
  let minutes = Math.floor(time / 60);
  let second = time - (minutes * 60);
  if (timers.length > 0) {
    timers.forEach(function(item) {
      clearTimeout(item);
    })
  }
  let timer = setInterval(function() {
    let newTime = formateTime(minutes, second, second--);
    second = newTime.second;
    minutes = newTime.minutes;
    nodeCode.innerHTML = newTime.time;
    time--;
    if (!time) stop();
  }, 1000);
  timers.push(timer);
  function stop () {
    clearInterval(timer);
    nodeCode.parentElement.classList.add('active');
    nodeCode.innerHTML = '';
    timers.pop();
  };
}

function codeSettings (timeResp) {
  durationCode = timeResp;
}


const collectTemplate = (content) => {
  const template = `
          <div class="popup">
              <img src="/local/templates/desktop/images/logo-blue.png" class="popuplogo" alt="Логотип Текстель">
              <div class="popupContent">${content}</div>
              <button class="popupClose popupClose-js reset-btn"><svg class="svg__fill close__popup-svg">
                <use xlink:href="/local/templates/desktop/images/sprite-vector.svg#close"></use>
                </svg>
              </button>
          </div>`;
  return template;
};

const changeContent = (item, newContent, close) => {
  item.querySelector(".popupContent").innerHTML = newContent;
  if (close) item.querySelector(".popupContent").innerHTML += close;
  return item;
};

class Popup {
  constructor(content, onCloseHandler) {
    this.content = content;
    this.onCloseHandler = onCloseHandler;
    this.isOpen = false;
    this.popupItem = null;

    this.getTemplate = this.getTemplate.bind(this);
    this._escCloseHandler = this._escCloseHandler.bind(this);
    this.close = this.close.bind(this);
  }

  getTemplate() {
    const wrapper = document.createElement("div");
    wrapper.classList.add("overlay");
    wrapper.innerHTML = collectTemplate(this.content);
    return wrapper;
  }

  open(newContent, close) {
    if (newContent) {
      changeContent(this.popupItem, newContent, close);
    }
    this.isOpen = true;
    this.setListner();
    this._changeClassName();
  }

  close() {
    this.isOpen = false;
    this._changeClassName();
    this.removeListner();
    if (this.onCloseHandler) this.onCloseHandler();
  }

  _changeClassName() {
    this.isOpen && this.popupItem
        ? this.popupItem.classList.add("active")
        : this.popupItem.classList.remove("active");
  }

  _escCloseHandler(evt) {
    if (evt.keyCode === 27) {
      this.close();
    }
  }

  setListner() {
    document.addEventListener("keydown", this._escCloseHandler);
    const allCloseBnts = this.popupItem.querySelectorAll(".popupClose-js");
    for (let i = 0; i < allCloseBnts.length; i++) {
      const element = allCloseBnts[i];
      element.addEventListener("click", this.close);
    }
  }

  removeListner() {
    document.removeEventListener("keydown", this._escCloseHandler);
  }

  init() {
    this.popupItem = this.getTemplate();
    document.body.appendChild(this.popupItem);
  }

  destroy() {
    this.popupItem.remove();
  }
}

let avalib = false;

const checkCheked = (selector, btn) => {
  let check = false;
  const list = document.querySelectorAll(selector);
  for (let i = 0; i < list.length; i++) {
    const el = list[i];
    if (el.checked) check = true;
  }
  return check;
};


const toggleDisableBtn = (isActive, btn) => {
  isActive ? btn.classList.remove("disable") : btn.classList.add("disable");
};

const bonusList = document.querySelector("#bounusList");
if (bonusList) {
  const popupBonus = new Popup("test");
  popupBonus.init();
  document.addEventListener("click", (evt) => {
    const { target } = evt;
    if (!target) return false;
    if (target.classList.contains("lk-bonus__present-btn--more")) {
      const id = evt.target.getAttribute("bonus-id");
      popupBonus.open(
          dataBonus[id],
          '<button style="margin: 10px auto 0;" class="round-btn blue-btn popupClose-js">Закрыть</button>'
      );
    }
    if (target.classList.contains("lk-bonus__present-btn--buy")) {
      const idProduct = target.getAttribute("bonus-id");
      currenID = idProduct;
      const data = document.querySelector("#popup-buy-content");
      popupBonus.open(data.innerHTML);
      popupHandler(idProduct, true, popupBonus);
    }

    if (target.classList.contains("popup-buy__resend-btn")) {
      if (!currenID) return false;
      popupHandler(currenID, false, popupBonus);
    }

    if (target.classList.contains("load-prav")) {
      document.querySelector('.prav-content').classList.add('active');
      target.remove();
    }
  });
}

const popupHandler = (idProduct, setHandler, popupBonus) => {
  const nominal = document.querySelector(
      '.lk-bonus__present-item[bonus-id="' +
      idProduct +
      '"] .lk-bonus__present-price-select'
  ).value;
  const nameSertBl = document.querySelector('.lk-bonus__present-item[bonus-id="' + idProduct +'"] .lk-bonus__present-btn.blue-btn');
  const nameSert = nameSertBl ? nameSertBl.getAttribute('bonus-name') : '';
  const formData = new FormData();
  formData.append("action", "send_sms");
  formData.append("nominal", nominal);
  formData.append("product_id", idProduct);
  fetch("/local/ajax/bp.php", {
    method: "POST",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
    body: formData,
  }).then((res) => res.json())
      .then((res)=> {
        if (res.idle_time) durationCode = res.idle_time;
        const form = document.querySelector(".popupContent .popup-buy__form");
        if (form) {
          form.setAttribute('data-id-sert', idProduct);
          form.setAttribute('data-nominal-sert', nominal);
          form.setAttribute('data-bonus-name', nameSert);
          const nodeCode = form.querySelector('.popup-buy__resend-btn span');
          setTimeCode(nodeCode);
          if (setHandler) formHandler(form, popupBonus);
          if (setHandler) checkerHandler(form);
        }
      })
}

const formHandler = (form, popupBonus) => {
  form.addEventListener("submit", (evt) => {
    evt.preventDefault();
    if (avalib) {
      const idSert = form.getAttribute('data-id-sert');
      const nominalSert = form.getAttribute('data-nominal-sert');
      const nameSert = form.getAttribute('data-bonus-name');
      const formData = new FormData(form);
      formData.append("action", "buy_card");
      fetch("/local/ajax/bp.php", {
        method: "POST",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        body: formData,
      }).then((res) => res.json())
          .then(res => {
            if (res.idle_time) durationCode = res.idle_time;
            if (res && res.message) {
              const alert = document.createElement('div');
              alert.classList.add('popupErrorCode');
              if (res.status) {
                alert.classList.add('alert-success');
                executePayment(res.transaction_id, idSert, nominalSert, nameSert, popupBonus);
              }
              alert.innerHTML = res.message;
              form.querySelector('.popup-buy__code').appendChild(alert);

              setTimeout(() => {
                alert.remove();
              }, 4000);
            }
          })
    }
  });
}

const checkerHandler = () => {
  let check1 = true;
  let check2 = false;

  const checkbox = document.querySelectorAll(
      ".popupContent .popup-buy__lab-bl .check__input"
  );
  const btnForm = document.querySelector(".popupContent .popup-buy__btn");
  for (let i = 0; i < checkbox.length; i++) {
    const el = checkbox[i];
    el.addEventListener("change", () => {
      check1 = checkCheked(
          ".popupContent .popup-buy__lab-bl .check__input"
      );
      toggleDisableBtn(check1 && check2, btnForm);
      avalib = check1 && check2;
    });
  }

  const checkPolicyti = document.querySelector(
      ".popupContent .popup-buy__policy input"
  );
  if (checkPolicyti) {
    checkPolicyti.addEventListener("change", () => {
      check2 = checkCheked(".popupContent .popup-buy__policy input");
      toggleDisableBtn(check1 && check2, btnForm);
      avalib = check1 && check2;
    });
  }
  toggleDisableBtn(check1 && check2, btnForm);
  avalib = check1 && check2;
}
