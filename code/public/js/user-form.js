const formAddUserEl = document.querySelector("form.add_user");
const inputFormEls = formAddUserEl.querySelectorAll(".input");
formAddUserEl.addEventListener("submit", function (e) {
  inputFormEls.forEach((element) => {
    if (element.value === "") {
      e.preventDefault();
      element.value === ""
        ? element.classList.add("error")
        : element.classList.remove("error");
    }
  });
});

inputFormEls.forEach(element => {
  
  element.addEventListener("input", function (e) {
    e.target.value !== ""
      ? e.target.classList.remove("error")
      : e.target.classList.add("error");
  });
  
  element.addEventListener("focus", function (e) {
    if (e.target.value === "") e.target.classList.add("error");
  });
});
