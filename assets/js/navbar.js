
const options = document.querySelectorAll('#options');
const optionsHolder = document.querySelectorAll('#optionsHolder');
options.forEach((el,index)=>{


         

         el.onclick = () => {
                  console.log(el);
                  if (el.classList.contains('click')) {
                           optionsHolder[index].classList.remove('active')
                           el.classList.remove('click')
                  } else {
                           
                           el.classList.add('click')
                           show(index)
                  }
                  
         }
         
})

function show(index){
         optionsHolder.forEach((el, theindex)=>{
                  if(index == theindex){
                           el.classList.add('active')
                  }
         })
}