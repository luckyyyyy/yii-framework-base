#include<stdio.h>
int main()
{
    int a[10] = {1,2,3,4,5,6,7,8,9,0};
    int *p;
    int (*c)[10] = &a;

    printf("%p\n", c);
    printf("%p\n", a + 10);

    for (p = a; p < ( a + 10 ); p++)
    {
        printf("%d\n", *p);
    }
    return 0;
}
