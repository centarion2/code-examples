const excelBtns = document.querySelector('.basket-excel');
if (excelBtns) {
    const infoPopup = document.querySelector('.alert-msg--info');
    excelBtns.addEventListener('click', function (evt) {
        const target = evt.currentTarget;
        target.parentElement.classList.add('active');
        if (infoPopup) {
            infoPopup.classList.remove('active');
        }
    });

    const closeInfoPupup = document.querySelector('.close-info');
    if (closeInfoPupup) {
        closeInfoPupup.addEventListener('click', function (evt) {
            infoPopup.classList.remove('active');
        });
    }

    const closeExcelBtns = document.querySelectorAll('.basket-excel__wrapper .close-excel');
    for(let i = 0; i < closeExcelBtns.length; i++) {
        closeExcelBtns[i].addEventListener('click', function (evt) {
            document.querySelector('.basket-excel__wrapper').classList.remove('active');
        });
    }
}

const fileInput = document.querySelector('.file-label input[type="file"]');
if (fileInput) {
    const fileName = document.querySelector('.file-label .file-name');
    fileInput.addEventListener('change', function() {
        fileName.innerHTML = fileInput.files[0].name;
    })
}