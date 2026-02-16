import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { CommonModule } from '@angular/common';

@Component({
  standalone: true,
  selector: 'app-login',
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginComponent {

  private fb = inject(FormBuilder);
  private auth = inject(AuthService);
  private router = inject(Router);

  loading = false;
  error = '';

  form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required]
  });

  submit(): void {
  console.log('FORM VALUE:', this.form.value);

  const { email, password } = this.form.getRawValue();

  console.log('EMAIL =>', `"${email}"`);
  console.log('PASSWORD =>', `"${password}"`);

  this.auth.login(email, password).subscribe({
    next: (res) => {
      console.log('LOGIN OK', res);
      this.router.navigateByUrl('/');
    },
    error: (err) => {
      console.error('LOGIN ERROR', err);
      this.error = 'Credenciales incorrectas';
      this.loading = false;
    }
  });
}

}
